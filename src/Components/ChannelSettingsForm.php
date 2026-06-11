<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Condoedge\Utils\Kompo\Common\Form;
use Kompo\Auth\Facades\UserModel;

class ChannelSettingsForm extends Form
{
	public const AVAILABLE_USERS_LIMIT = 100;

	public $model = Channel::class;

	public function created()
	{
		// authorize() only guards submit/ajax in Kompo; this also guards the GET render
		if (!$this->authorize()) {
			abort(403);
		}
	}

	public function authorize()
	{
		// Creating a new channel is open to any team member; editing requires being a participant
		return !$this->model->id || auth()->user()->can('update', $this->model);
	}

	public function beforeSave()
	{
		$this->model->setTeamId();
	}

	public function response()
	{
		return redirect()->route('discussions', [
			'channel_id' => $this->model->id
		]);
	}

	public function render()
	{
		return _Div(

	        _FlexBetween(
	        	_Columns(
	        		_Rows(
		        		_MiniTitle('discussions.name')->class('mb-2'),
						_Input()->placeholder('discussions.channel-name')->name('name')
					),
	        		_Rows(
		        		_MiniTitle('discussions.owner')->class('mb-3'),
						_Html('<span class="vlTagOutlined">'.($this->model->addedBy->name ?? auth()->user()->name).'</span>'),
					),
	        	)->class('flex-auto'),
				$this->model->id ?

					_Link('discussions.back')->icon('arrow-left')->selfGet('getChannel', [
						'id' => $this->model->id
					])->inPanel('channel-view-panel') :

					_Link('discussions.back')->icon('arrow-left')->href('discussions')
			)->alignStart(),

	        _MiniTitle('discussions.members')->class('mb-2'),

			_MultiSelect()->placeholder(__('discussions.add-members'))->name('users')
	        	->searchOptions(3, 'getAvailableTeamUsers', 'retrieveUsers'),

			_SubmitButton('discussions.save'),

		)->class('p-4');
	}

	public function getAvailableTeamUsers($search)
	{
		if (method_exists(currentTeam(), 'getAvailableUsersForChannel')) {
			return UserModel::buildDisambiguatedOptions(
				currentTeam()->getAvailableUsersForChannel($this->model, $search)
			);
		}

		$users = currentTeam()->users()->where('users.id', '!=', auth()->user()->id)
			->take(static::AVAILABLE_USERS_LIMIT)->search($search)
			->get();

		return UserModel::buildDisambiguatedOptions($users);
	}

	public function retrieveUsers($user)
	{
		return [$user->id => _Html($user->name)];
	}

	public function getChannel($id)
	{
		$channel = Channel::findOrFail($id);

		if (!auth()->user()->can('view', $channel)) {
			abort(403);
		}

		return new ChannelDiscussionsPanel([
            'channel_id' => $id,
        ]);
	}

	public function rules()
	{
		return [
			'name' => 'required',
		];
	}
}
