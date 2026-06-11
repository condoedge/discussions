<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Condoedge\Utils\Kompo\Common\Modal;
use Kompo\Auth\Facades\UserModel;

class ChannelSettingsForm extends Modal
{
	public const AVAILABLE_USERS_LIMIT = 100;

	public $model = Channel::class;

	public $_Title = 'discussions.channel-settings';

	protected $noHeaderButtons = true;

	public function created()
	{
		// authorize() only guards submit/ajax in Kompo; this also guards the GET render
		if (!$this->authorize()) {
			abort(403);
		}

		if (!$this->model->id) {
			$this->_Title = 'discussions.new-channel';
		}
	}

	public function authorize()
	{
		// Creating a new channel is open to any team member; editing requires being a participant
		return !$this->model->id || auth()->user()->can('update', $this->model);
	}

	protected $memberIdsBeforeSave = [];

	public function beforeSave()
	{
		$this->model->setTeamId();

		$this->memberIdsBeforeSave = $this->model->id
			? $this->model->users()->pluck('users.id')->all()
			: [];
	}

	public function afterSave()
	{
		// Notify only NEWLY added members (the host app listens and creates
		// its own notification); message arrivals don't notify anymore
		$this->model->users()->pluck('users.id')
			->diff($this->memberIdsBeforeSave)
			->reject(fn($id) => (int) $id === (int) auth()->id())
			->each(fn($id) => event(new \Kompo\Discussions\Events\MemberAddedToChannel($this->model, $id)));
	}

	public function response()
	{
		return redirect()->route('discussions', [
			'channel_id' => $this->model->id
		]);
	}

	public function body()
	{
		return _Rows(
			_Input('discussions.name')->placeholder('discussions.channel-name')->name('name'),

			_MultiSelect('discussions.members')->placeholder(__('discussions.add-members'))->name('users')
				->searchOptions(3, 'getAvailableTeamUsers', 'retrieveUsers'),

			_Html(__('discussions.owner').': '.($this->model->addedBy->name ?? auth()->user()->name))
				->class('text-sm text-gray-500 mb-6'),

			_SubmitButton('discussions.save')->class('w-full'),
		);
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

	public function rules()
	{
		return [
			'name' => 'required',
		];
	}
}
