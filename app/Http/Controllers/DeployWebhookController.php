<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GitHub "push" webhook. Verifies the HMAC signature, and on a push to the
 * deploy branch starts deploy/update.sh in the background (via a sudoers rule
 * installed by deploy/enable-autodeploy.sh). Disabled unless DEPLOY_WEBHOOK_SECRET is set.
 */
class DeployWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.deploy.secret');

        if ($secret === '') {
            abort(404);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403, 'Bad signature');

        $event = $request->header('X-GitHub-Event');

        if ($event === 'ping') {
            return response('pong');
        }

        if ($event !== 'push' || $request->input('ref') !== 'refs/heads/'.config('services.deploy.branch')) {
            return response('Ignored', 202);
        }

        $script = base_path('deploy/update.sh');
        $log = storage_path('logs/deploy.log');

        // Detached so the webhook answers immediately; nohup keeps it alive past php-fpm's request.
        exec('nohup sudo -n '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1 &');

        return response('Deploy started', 202);
    }
}
