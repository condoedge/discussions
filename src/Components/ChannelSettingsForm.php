<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Condoedge\Utils\Kompo\Common\Form;

class ChannelSettingsForm extends Form
{
    public $model = Channel::class;

    public $class = 'max-w-2xl';

    protected const ICON_OPTIONS = [
        'messages', 'lamp-on', 'direct-inbox', 'calendar',
        'folder-2', 'book', 'heart', 'star',
    ];

    public function beforeSave()
    {
        $this->model->setTeamId();
        $this->model->icon = request('icon') ?: Channel::DEFAULT_ICON;
        $this->model->color = request('color') ?: Channel::DEFAULT_COLOR;
    }

    public function response()
    {
        return redirect()->route('discussions', ['channel_id' => $this->model->id]);
    }

    // ── LAYOUT ──

    public function render()
    {
        return _Rows(
            $this->heroBanner(),
            _Rows(
                $this->nameSection(),
                $this->descriptionSection(),
                $this->iconPickerSection(),
                $this->colorPickerSection(),
                $this->visibilitySection(),
                $this->membersSection(),
                $this->footerActions(),
            )->class('p-6 gap-5'),
        );
    }

    // ── HERO ──

    protected function heroBanner()
    {
        $icon = $this->model->getIconName();
        $color = $this->model->getColorHex();

        return _Flex(
            _Rows(
                _Sax($icon, 40)->class('text-white'),
            )->id('channel-hero-preview')
             ->class('w-[76px] h-[76px] rounded-2xl flex items-center justify-center shadow-md')
             ->style('background-color: ' . $color . ';'),
            _Rows(
                _Html('discussions.new-channel-eyebrow')->class('text-xs uppercase tracking-wide text-white opacity-80'),
                _Html($this->model->id ? 'discussions.edit-channel' : 'discussions.new-channel-title')
                    ->class('text-xl font-bold text-white'),
                _Html('discussions.new-channel-subtitle')->class('text-xs text-white opacity-75 mt-1'),
            )->class('gap-0'),
        )->class('gap-4 items-center p-6 rounded-t-xl')
         ->style('background: linear-gradient(135deg, #0a6e4e 0%, #14b8a6 100%);');
    }

    // ── FIELDS ──

    protected function nameSection()
    {
        return _Rows(
            _MiniTitle('discussions.channel-name')->class('mb-1'),
            _Input()->name('name')->placeholder('discussions.channel-name-placeholder')->required(),
        );
    }

    protected function descriptionSection()
    {
        return _Rows(
            _MiniTitle('discussions.channel-description')->class('mb-1'),
            _Textarea()->name('description')->placeholder('discussions.channel-description-placeholder')
                ->rows(2),
        );
    }

    protected function iconPickerSection()
    {
        $current = $this->model->getIconName();

        return _Rows(
            _MiniTitle('discussions.channel-icon')->class('mb-2'),
            _Flex(
                ...collect(self::ICON_OPTIONS)->map(fn ($iconName) => $this->iconOption($iconName, $current))->all(),
            )->class('gap-2 flex-wrap'),
            _Hidden()->name('icon')->default($current)->id('channel-icon-field'),
        );
    }

    protected function iconOption(string $iconName, string $current)
    {
        $active = $iconName === $current;

        return _Flex(
            _Sax($iconName, 20)->class($active ? 'text-white' : 'text-gray-500'),
        )->class('w-10 h-10 rounded-lg items-center justify-center cursor-pointer transition '
            . ($active ? 'bg-greenmain shadow-sm' : 'bg-gray-100 hover:bg-gray-200'))
         ->attr(['data-icon' => $iconName])
         ->onClick(fn ($e) => $e->run('() => {
                document.querySelectorAll("[data-icon]").forEach(el => {
                    el.classList.remove("bg-greenmain", "shadow-sm");
                    el.classList.add("bg-gray-100");
                    el.querySelector("svg,img")?.classList.remove("text-white");
                    el.querySelector("svg,img")?.classList.add("text-gray-500");
                });
                const selected = event.currentTarget;
                selected.classList.remove("bg-gray-100");
                selected.classList.add("bg-greenmain", "shadow-sm");
                selected.querySelector("svg,img")?.classList.remove("text-gray-500");
                selected.querySelector("svg,img")?.classList.add("text-white");
                document.getElementById("channel-icon-field").value = selected.dataset.icon;
                document.getElementById("channel-icon-field").dispatchEvent(new Event("change"));
            }'));
    }

    protected function colorPickerSection()
    {
        $current = $this->model->color ?: Channel::DEFAULT_COLOR;

        return _Rows(
            _MiniTitle('discussions.channel-color')->class('mb-2'),
            _Flex(
                ...collect(Channel::COLOR_HEX)->map(fn ($hex, $key) => $this->colorOption($key, $hex, $current))->values()->all(),
            )->class('gap-2 flex-wrap'),
            _Hidden()->name('color')->default($current)->id('channel-color-field'),
        );
    }

    protected function colorOption(string $key, string $hex, string $current)
    {
        $active = $key === $current;

        return _Flex()->class('w-9 h-9 rounded-full cursor-pointer transition '
            . ($active ? 'ring-2 ring-offset-2 ring-gray-700' : 'hover:scale-110'))
         ->style('background-color: ' . $hex . ';')
         ->attr(['data-color' => $key, 'data-hex' => $hex])
         ->onClick(fn ($e) => $e->run('() => {
                document.querySelectorAll("[data-color]").forEach(el => {
                    el.classList.remove("ring-2", "ring-offset-2", "ring-gray-700");
                });
                const selected = event.currentTarget;
                selected.classList.add("ring-2", "ring-offset-2", "ring-gray-700");
                document.getElementById("channel-color-field").value = selected.dataset.color;
                const hero = document.getElementById("channel-hero-preview");
                if (hero) hero.style.backgroundColor = selected.dataset.hex;
            }'));
    }

    protected function visibilitySection()
    {
        $current = $this->model->is_private ? 'private' : 'public';

        return _Rows(
            _MiniTitle('discussions.channel-visibility')->class('mb-2'),
            _Flex(
                $this->visibilityCard('public', 'global', 'discussions.visibility-public', 'discussions.visibility-public-hint', $current),
                $this->visibilityCard('private', 'lock', 'discussions.visibility-private', 'discussions.visibility-private-hint', $current),
            )->class('gap-3'),
            _Hidden()->name('is_private')->default($this->model->is_private ? 1 : 0)->id('channel-visibility-field'),
        );
    }

    protected function visibilityCard(string $key, string $icon, string $labelKey, string $hintKey, string $current)
    {
        $active = $key === $current;
        $isPrivate = $key === 'private' ? 1 : 0;

        return _Rows(
            _Flex(
                _Sax($icon, 18)->class($active ? 'text-greenmain' : 'text-gray-400'),
                _Html($labelKey)->class('text-sm font-semibold'),
            )->class('gap-2 items-center mb-1'),
            _Html($hintKey)->class('text-xs text-gray-500'),
        )->class('flex-1 cursor-pointer rounded-lg border-2 p-4 transition '
            . ($active ? 'border-greenmain bg-greenmain bg-opacity-5' : 'border-gray-200 hover:border-gray-300'))
         ->attr(['data-visibility' => $key])
         ->onClick(fn ($e) => $e->run('() => {
                document.querySelectorAll("[data-visibility]").forEach(el => {
                    el.classList.remove("border-greenmain", "bg-greenmain", "bg-opacity-5");
                    el.classList.add("border-gray-200");
                });
                const selected = event.currentTarget;
                selected.classList.remove("border-gray-200");
                selected.classList.add("border-greenmain", "bg-greenmain", "bg-opacity-5");
                document.getElementById("channel-visibility-field").value = ' . $isPrivate . ';
            }'));
    }

    protected function membersSection()
    {
        return _Rows(
            _MiniTitle('discussions.members')->class('mb-2'),
            _MultiSelect()->placeholder('discussions.add-members')->name('users')
                ->searchOptions(2, 'getAvailableTeamUsers', 'retrieveUsers'),
        );
    }

    protected function footerActions()
    {
        return _FlexBetween(
            _Html($this->model->id ? '' : 'discussions.creator-hint')->class('text-xs text-gray-500'),
            _Flex(
                _Link('generic.cancel')->closeModal()->outlined(),
                _SubmitButton($this->model->id ? 'generic.save' : 'discussions.create-channel')
                    ->closeModal()->redirect(),
            )->class('gap-2'),
        )->class('pt-3 border-t border-gray-100');
    }

    // ── DATA ──

    public function getAvailableTeamUsers($search)
    {
        if (method_exists(currentTeam(), 'getAvailableUsersForChannel')) {
            return currentTeam()->getAvailableUsersForChannel($this->model, $search)
                ->mapWithKeys(fn ($user) => [$user->id => _Html($user->name)]);
        }

        return currentTeam()->users()->where('users.id', '!=', auth()->user()->id)
            ->take(100)->search($search)
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => _Html($user->name)]);
    }

    public function retrieveUsers($users)
    {
        return [$users->id => _Html($users->name)];
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:64',
            'color' => 'nullable|string|max:32',
            'is_private' => 'nullable|boolean',
        ];
    }
}
