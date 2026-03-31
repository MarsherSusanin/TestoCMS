<?php

namespace App\Http\Controllers;

use App\Modules\Setup\Services\DeploymentProfileService;
use App\Modules\Setup\Services\EnvWriterService;
use App\Modules\Setup\Services\SetupFinalizationService;
use App\Modules\Setup\Services\SystemCheckService;
use Illuminate\Http\Request;

class SetupWizardController extends Controller
{
    public function __construct(
        private readonly EnvWriterService $envWriter,
        private readonly SystemCheckService $systemCheck,
        private readonly DeploymentProfileService $deploymentProfiles,
        private readonly SetupFinalizationService $setupFinalizer,
    ) {}

    /**
     * Step 1 — System requirements check.
     */
    public function step1()
    {
        $this->abortIfInstalled();

        $checks = $this->systemCheck->runAll();
        $allPassed = $this->systemCheck->allRequiredPassed();

        return view('setup.step1-system', compact('checks', 'allPassed'));
    }

    /**
     * Step 2 — Database configuration form.
     */
    public function step2()
    {
        $this->abortIfInstalled();

        $auto = $this->systemCheck->autoDetect();

        return view('setup.step2-database', compact('auto'));
    }

    /**
     * Step 2 POST — Test database connection (AJAX).
     */
    public function testDatabase(Request $request)
    {
        $this->abortIfInstalled();

        $request->validate([
            'db_connection' => 'required|in:mysql,pgsql',
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);

        $result = $this->envWriter->testDatabaseConnection([
            'driver' => $request->input('db_connection'),
            'host' => $request->input('db_host'),
            'port' => $request->input('db_port'),
            'database' => $request->input('db_database'),
            'username' => $request->input('db_username'),
            'password' => $request->input('db_password', ''),
        ]);

        return response()->json($result);
    }

    /**
     * Step 2 POST — Save database config and proceed.
     */
    public function saveStep2(Request $request)
    {
        $this->abortIfInstalled();

        $validated = $request->validate([
            'db_connection' => 'required|in:mysql,pgsql',
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|numeric',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);

        $request->session()->put('setup.db', $validated);

        return redirect()->route('setup.step3');
    }

    /**
     * Step 3 — Site settings form.
     */
    public function step3(Request $request)
    {
        $this->abortIfInstalled();

        if (! $request->session()->has('setup.db')) {
            return redirect()->route('setup.step2');
        }

        $auto = $this->systemCheck->autoDetect();
        $profiles = $this->deploymentProfiles->all();
        $defaultDeploymentProfile = $this->deploymentProfiles->default();

        return view('setup.step3-site', compact('auto', 'profiles', 'defaultDeploymentProfile'));
    }

    /**
     * Step 3 POST — Save site settings.
     */
    public function saveStep3(Request $request)
    {
        $this->abortIfInstalled();

        $validated = $request->validate([
            'deployment_profile' => 'required|string|in:shared_hosting,docker_vps',
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url|max:255',
            'supported_locales' => 'required|array|min:1',
            'supported_locales.*' => 'string|in:ru,en',
            'default_locale' => 'required|string|in:ru,en',
        ]);

        $request->session()->put('setup.site', $validated);

        return redirect()->route('setup.step4');
    }

    /**
     * Step 4 — Admin account form.
     */
    public function step4(Request $request)
    {
        $this->abortIfInstalled();

        if (! $request->session()->has('setup.db') || ! $request->session()->has('setup.site')) {
            return redirect()->route('setup.step2');
        }

        return view('setup.step4-admin');
    }

    /**
     * Step 4 POST — Save admin and finalize.
     */
    public function saveStep4(Request $request)
    {
        $this->abortIfInstalled();

        $validated = $request->validate([
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_login' => 'required|string|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        $request->session()->put('setup.admin', $validated);

        return redirect()->route('setup.step5');
    }

    /**
     * Step 5 — Finalize installation.
     */
    public function step5(Request $request)
    {
        $this->abortIfInstalled();

        $db = $request->session()->get('setup.db');
        $site = $request->session()->get('setup.site');
        $admin = $request->session()->get('setup.admin');

        if (! $db || ! $site || ! $admin) {
            return redirect()->route('setup.step2');
        }

        $auto = $this->systemCheck->autoDetect();

        $envData = array_merge($auto, $db, $site, $admin);
        $result = $this->setupFinalizer->finalize($envData);

        if (! $result['hasErrors']) {
            $request->session()->forget('setup');
        }

        $steps = $result['steps'];
        $errors = $result['errors'];
        $hasErrors = $result['hasErrors'];

        return view('setup.step5-finish', compact('steps', 'errors', 'hasErrors'));
    }

    private function abortIfInstalled(): void
    {
        if (EnvWriterService::isInstalled() && ! app()->runningUnitTests()) {
            abort(404);
        }
    }
}
