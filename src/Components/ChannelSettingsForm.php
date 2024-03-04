<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Form;

class ChannelSettingsForm extends Form
{
	public $model = Channel::class;

	public $style = 'height:calc(100vh - 200px)';

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
		        		_MiniTitle('Name')->class('mb-2'),
						_Input()->placeholder('discussions.channel-name')->name('name')
					),
	        		_Rows(
		        		_MiniTitle('discussions.owner')->class('mb-3'),
						_Html('<span class="vlTagOutlined">'.auth()->user()->name.'</span>'),
					),
	        	)->class('flex-auto'),
				$this->model->id ?

					_Link('Back')->icon('arrow-left')->selfGet('getChannel', [
						'id' => $this->model->id
					])->inPanel('channel-view-panel') :

					_Link('Back')->icon('arrow-left')->href('discussions')
			)->alignStart(),

	        _MiniTitle('Members')->class('mb-2'),

			_MultiSelect()->placeholder(__('discussions.add-members'))->name('users')
	        	->options(currentTeam()->users()->where('users.id', '!=', auth()->user()->id)->get()
	        		->mapWithKeys(fn($user) => [
	        			$user->id => _Html($user->name)
	        		])
	        	),

			_SubmitButton('Save'),

		)->class('p-4');
	}

	public function getChannel($id)
	{
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
