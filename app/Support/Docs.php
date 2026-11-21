<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * In-app documentation. Reads the markdown files in /docs, decides which guides each user may see,
 * and renders them to safe HTML with anchors and a table of contents.
 */
class Docs
{
    /** @return array<string, array{title:string, file:string, icon:string, summary:string, audience:string, can:\Closure}> */
    public static function registry(): array
    {
        return [
            'roles' => [
                'title' => 'Roles & responsibilities', 'file' => 'roles.md', 'icon' => 'user-group',
                'summary' => 'What each role can do, where to start and the daily routine.', 'audience' => 'Everyone',
                'can' => fn (User $u) => true,
            ],
            'customer-guide' => [
                'title' => 'Buying on Village City', 'file' => 'customer-guide.md', 'icon' => 'shopping-bag',
                'summary' => 'Create an account, order produce, track delivery, pay and reorder.', 'audience' => 'Everyone',
                'can' => fn (User $u) => true,
            ],
            'user-guide' => [
                'title' => 'Staff user guide', 'file' => 'user-guide.md', 'icon' => 'book-open',
                'summary' => 'Step-by-step workflows: farmers, procurement, orders, dispatch, payments, inventory, reports.', 'audience' => 'Staff',
                'can' => fn (User $u) => $u->can('dashboard.view'),
            ],
            'modules' => [
                'title' => 'Module reference', 'file' => 'modules.md', 'icon' => 'squares-2x2',
                'summary' => 'Every page, its permission and what it does, plus the list of reports.', 'audience' => 'Staff',
                'can' => fn (User $u) => $u->can('dashboard.view'),
            ],
            'seo-guide' => [
                'title' => 'SEO & content guide', 'file' => 'seo-guide.md', 'icon' => 'megaphone',
                'summary' => 'What the site does for search engines, how to manage SEO and website content, and the go-live checklist.', 'audience' => 'Administrators & SEO managers',
                'can' => fn (User $u) => $u->can('seo.manage'),
            ],
            'setup' => [
                'title' => 'Setup & operations', 'file' => 'setup.md', 'icon' => 'wrench-screwdriver',
                'summary' => 'Installation, configuration, seeding, production checklist and troubleshooting.', 'audience' => 'Administrators',
                'can' => fn (User $u) => $u->can('settings.manage') || $u->can('roles.manage'),
            ],
            'architecture' => [
                'title' => 'Architecture', 'file' => 'architecture.md', 'icon' => 'cpu-chip',
                'summary' => 'Code structure, data model, business rules, security and extension points.', 'audience' => 'Administrators',
                'can' => fn (User $u) => $u->can('settings.manage') || $u->can('roles.manage'),
            ],
        ];
    }

    /** Documents this user is allowed to open. */
    public static function available(User $user): array
    {
        return array_filter(self::registry(), fn ($doc) => $doc['can']($user));
    }

    public static function exists(string $slug): bool
    {
        return isset(self::registry()[$slug]);
    }

    protected static function path(string $file): string
    {
        return base_path('docs/'.$file);
    }

    /** @return array{html:string, toc:list<array{level:int, id:string, text:string}>} */
    public static function render(string $slug): array
    {
        $doc = self::registry()[$slug];
        $path = self::path($doc['file']);

        if (! is_file($path)) {
            return ['html' => '<p>This guide is not available in this installation.</p>', 'toc' => []];
        }

        return Cache::remember('docs:'.$slug.':'.filemtime($path), 3600, function () use ($path) {
            $html = self::markdown((string) file_get_contents($path));

            preg_match_all('#<h([23]) id="([^"]+)"[^>]*>(.*?)</h\1>#s', $html, $m, PREG_SET_ORDER);
            $toc = array_map(fn ($h) => ['level' => (int) $h[1], 'id' => $h[2], 'text' => trim(strip_tags($h[3]))], $m);

            return ['html' => $html, 'toc' => $toc];
        });
    }

    /** Markdown to safe HTML: raw HTML stripped, headings get ids, doc links point at the in-app pages. */
    public static function markdown(string $markdown): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['apply_id_to_heading' => true, 'insert' => 'none', 'id_prefix' => '', 'fragment_prefix' => ''],
        ], [new HeadingPermalinkExtension]);

        // Links between guides (e.g. setup.md#section) become in-app links.
        $html = preg_replace_callback('#href="(?:\./|docs/)?([a-z-]+)\.md(\#[^"]*)?"#', function ($m) {
            return self::exists($m[1]) ? 'href="'.route('help.show', $m[1]).($m[2] ?? '').'"' : $m[0];
        }, $html);

        // External links open in a new tab.
        return preg_replace('#<a href="(https?://[^"]+)"#', '<a target="_blank" rel="noopener noreferrer" href="$1"', $html);
    }

    /** The "quick start" section of roles.md for this user's role, as HTML. */
    public static function roleQuickStart(User $user): ?string
    {
        $role = $user->roleName();
        $path = self::path('roles.md');

        if (! $role || ! is_file($path)) {
            return null;
        }

        if (! preg_match('/^## '.preg_quote($role, '/').'\s*$(.*?)(?=^## |\z)/ms', (string) file_get_contents($path), $m)) {
            return null;
        }

        return self::markdown(trim($m[1]));
    }

    /**
     * Search the guides this user can read.
     *
     * @return list<array{slug:string, title:string, hits:list<array{heading:string, text:string}>}>
     */
    public static function search(string $term, User $user): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $results = [];

        foreach (self::available($user) as $slug => $doc) {
            $path = self::path($doc['file']);

            if (! is_file($path)) {
                continue;
            }

            $heading = $doc['title'];
            $hits = [];

            foreach (preg_split('/\R/', (string) file_get_contents($path)) as $line) {
                if (preg_match('/^#{1,4}\s+(.*)$/', $line, $h)) {
                    $heading = trim($h[1]);

                    continue;
                }

                if (stripos($line, $term) !== false && count($hits) < 4) {
                    $hits[] = ['heading' => $heading, 'text' => Str::limit(trim(preg_replace('/[*_`|>#-]+/', ' ', $line)), 160)];
                }
            }

            if ($hits) {
                $results[] = ['slug' => $slug, 'title' => $doc['title'], 'hits' => $hits];
            }
        }

        return $results;
    }
}
