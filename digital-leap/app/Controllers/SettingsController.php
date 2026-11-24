<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Preference;
use Leap\Core\View;

/**
 * The settings each account owns for itself: which notifications still earn
 * an email copy, how large the text reads, and what this device holds offline.
 * Coordinator-wide application settings stay on the administrator screen.
 */
final class SettingsController
{
    public function show(): void
    {
        View::render('settings/index', [
            'title' => 'My settings',
            'userId' => (int) Auth::id(),
            'prefs' => Preference::forUser((int) Auth::id()),
        ]);
    }

    public function save(): void
    {
        $userId = (int) Auth::id();
        $before = Preference::forUser($userId);

        $size = (string) \post('text_size', 'normal');
        if (!in_array($size, Preference::TEXT_SIZES, true)) {
            $size = 'normal';
        }

        $wanted = [
            'ui.text_size' => $size,
            'ui.reduce_motion' => \post('reduce_motion') ? 'on' : 'off',
        ];
        $families = (array) \post('email', []);
        foreach (array_keys(Preference::FAMILIES) as $family) {
            $wanted['notify.email.' . $family] = isset($families[$family]) ? 'on' : 'off';
        }

        foreach ($wanted as $key => $value) {
            if ($value !== ($before[$key] ?? $this->defaultFor($key))) {
                Preference::set($userId, $key, $value);
            }
        }

        $after = Preference::forUser($userId);
        Audit::log('settings.updated', 'user', $userId, $before, $after);
        flash('success', 'Your settings are saved.');
        redirect('settings');
    }

    private function defaultFor(string $key): string
    {
        if (str_starts_with($key, 'notify.email.')) {
            return 'on';
        }
        return $key === 'ui.reduce_motion' ? 'off' : 'normal';
    }
}
