<?php

namespace App\Livewire\Crud;

use App\Livewire\Concerns\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Reusable list + create/edit/delete page for master data.
 * Subclasses describe the model, table columns, form fields and filters.
 */
abstract class ResourcePage extends Component
{
    use Page, WithFileUploads, WithPagination;

    #[\Livewire\Attributes\Url]
    public string $search = '';

    public array $filters = [];

    public array $form = [];

    public ?int $editingId = null;

    public bool $showForm = false;

    public ?int $deletingId = null;

    public bool $showDelete = false;

    public $upload = null;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function title(): string;

    /** Permission module, e.g. "farmers" gives farmers.view / farmers.manage */
    abstract protected function module(): string;

    /** @return list<array{label:string, value:\Closure, badge?:bool, class?:string}> */
    abstract protected function columns(): array;

    /** @return array<string, array<string, mixed>> */
    abstract protected function fields(): array;

    protected function singular(): string
    {
        return Str::singular($this->title());
    }

    protected function description(): string
    {
        return '';
    }

    /** @return array<string, array{label:string, options:array, apply?:\Closure}> */
    protected function filterDefinitions(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function searchable(): array
    {
        return ['name'];
    }

    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /** Route name for a detail page, receiving the row as parameter. */
    /** Optional banner photo, e.g. site/hero-farmer (public/images/site/hero-farmer.jpg). */
    protected function banner(): ?string
    {
        return null;
    }

    protected function showRoute(): ?string
    {
        return null;
    }

    protected function deletable(): bool
    {
        return true;
    }

    protected function editable(): bool
    {
        return true;
    }

    /** @return list<array{0:string, 1:string, 2:string}> label, url, icon */
    protected function headerLinks(): array
    {
        return [];
    }

    /** Create or update the record. Override to route writes through a service. */
    protected function persist(array $data, ?Model $record): Model
    {
        if ($record) {
            $record->update($data);

            return $record;
        }

        return $this->model()::create($data);
    }

    protected function hasStatusToggle(): bool
    {
        return array_key_exists('status', $this->fields()) && ($this->fields()['status']['toggle'] ?? true);
    }

    /** @return list<array{label:string, icon:string, method:string, show?:\Closure, confirm?:string}> */
    protected function rowActions(): array
    {
        return [];
    }

    protected function beforeSave(array $data, ?Model $record): array
    {
        return $data;
    }

    protected function afterSave(Model $record): void {}

    protected function fillExtra(Model $record): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->guard($this->module().'.view');
        $this->filters = array_fill_keys(array_keys($this->filterDefinitions()), '');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->guard($this->module().'.manage');
        $this->resetValidation();
        $this->editingId = null;
        $this->upload = null;
        $this->form = [];

        foreach ($this->fields() as $name => $field) {
            $this->form[$name] = $field['default'] ?? (($field['type'] ?? 'text') === 'checkboxes' ? [] : (($field['type'] ?? '') === 'switch' ? false : ''));
        }

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->guard($this->module().'.manage');
        abort_unless($this->editable(), 403);
        $this->resetValidation();
        $record = $this->query()->findOrFail($id);
        $this->editingId = $record->getKey();
        $this->upload = null;
        $this->form = [];

        foreach ($this->fields() as $name => $field) {
            $type = $field['type'] ?? 'text';

            $this->form[$name] = match (true) {
                $type === 'password', $type === 'image' => '',
                $type === 'checkboxes' => $record->{$field['relation']}()->pluck($record->{$field['relation']}()->getRelated()->getQualifiedKeyName())->map(fn ($v) => (string) $v)->all(),
                $type === 'switch' => (bool) $record->{$name},
                $type === 'datetime' => $record->{$name} ? \Illuminate\Support\Carbon::parse($record->{$name})->format('Y-m-d\TH:i') : '',
                $type === 'date' => $record->{$name} ? \Illuminate\Support\Carbon::parse($record->{$name})->format('Y-m-d') : '',
                default => $record->getRawOriginal($name) ?? '',
            };
        }

        $this->form = array_merge($this->form, $this->fillExtra($record));
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->guard($this->module().'.manage');

        $record = $this->editingId ? $this->query()->findOrFail($this->editingId) : null;
        $fields = $this->fields();
        $rules = [];
        $names = [];

        foreach ($fields as $name => $field) {
            $fieldRules = $field['rules'] ?? ['nullable'];
            $fieldRules = $fieldRules instanceof \Closure ? $fieldRules($record) : $fieldRules;

            if (($field['type'] ?? '') === 'image') {
                $rules['upload'] = ['nullable', 'image', 'max:2048'];
                $names['upload'] = strtolower($field['label']);

                continue;
            }

            $rules['form.'.$name] = $fieldRules;
            $names['form.'.$name] = strtolower($field['label']);
        }

        $this->validate($rules, [], $names);

        $data = [];
        $relations = [];

        foreach ($fields as $name => $field) {
            $type = $field['type'] ?? 'text';
            $value = $this->form[$name] ?? null;

            if ($type === 'checkboxes') {
                $relations[$field['relation']] = array_filter((array) $value);
            } elseif ($type === 'image') {
                if ($this->upload) {
                    $data[$name] = $this->upload->store($field['directory'] ?? 'uploads', 'public');
                }
            } elseif ($type === 'password') {
                if (filled($value)) {
                    $data[$name] = $value;
                }
            } elseif ($type === 'switch') {
                $data[$name] = (bool) $value;
            } else {
                $data[$name] = $value === '' ? null : $value;
            }
        }

        $saved = $this->attempt(function () use (&$record, $data, $relations) {
            $record = $this->persist($this->beforeSave($data, $record), $record);

            foreach ($relations as $relation => $ids) {
                $record->{$relation}()->sync($ids);
            }

            $this->afterSave($record);
        }, $this->singular().($this->editingId ? ' updated.' : ' created.'));

        if ($saved) {
            $this->showForm = false;
            $this->upload = null;
        }
    }

    public function toggleStatus(int $id): void
    {
        $this->guard($this->module().'.manage');
        $record = $this->query()->findOrFail($id);
        $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
        $this->toast($this->singular().' '.($record->status === 'active' ? 'activated.' : 'deactivated.'));
    }

    public function confirmDelete(int $id): void
    {
        $this->guard($this->module().'.manage');
        $this->deletingId = $id;
        $this->showDelete = true;
    }

    public function delete(): void
    {
        $this->guard($this->module().'.manage');
        abort_unless($this->deletable(), 403);

        try {
            $this->query()->findOrFail($this->deletingId)->delete();
            $this->toast($this->singular().' deleted.');
        } catch (QueryException) {
            $this->toast('This '.strtolower($this->singular()).' has related records and cannot be deleted. Deactivate it instead.', 'danger');
        }

        $this->showDelete = false;
        $this->deletingId = null;
    }

    protected function rows()
    {
        $query = $this->query();

        if ($this->search !== '' && $this->searchable() !== []) {
            $term = '%'.$this->search.'%';
            $query->where(function (Builder $q) use ($term) {
                foreach ($this->searchable() as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        }

        foreach ($this->filterDefinitions() as $name => $filter) {
            $value = $this->filters[$name] ?? '';

            if ($value === '' || $value === null) {
                continue;
            }

            isset($filter['apply']) ? $filter['apply']($query, $value) : $query->where($name, $value);
        }

        return $query->latest('id')->paginate(15);
    }

    public function render()
    {
        return $this->page('livewire.crud.index', [
            'rows' => $this->rows(),
            'title' => $this->title(),
            'singular' => $this->singular(),
            'description' => $this->description(),
            'columns' => $this->columns(),
            'fields' => Arr::map($this->fields(), fn ($f) => Arr::except($f, ['rules'])),
            'filterDefinitions' => $this->filterDefinitions(),
            'banner' => $this->banner(),
            'showRoute' => $this->showRoute(),
            'deletable' => $this->deletable(),
            'editable' => $this->editable(),
            'headerLinks' => $this->headerLinks(),
            'statusToggle' => $this->hasStatusToggle(),
            'rowActions' => $this->rowActions(),
            'canManage' => auth()->user()->can($this->module().'.manage'),
            'hasSearch' => $this->searchable() !== [],
        ], $this->title());
    }
}
