<?php

namespace Kompo\Discussions\Components;

use Condoedge\Utils\Kompo\Chat\ChatBubbleRenderer;
use Condoedge\Utils\Kompo\Chat\ChatComposerForm;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;

class DiscussionForm extends ChatComposerForm
{
	public $model = Discussion::class;

	protected $channelId;
	protected $channel;
	protected $discussionId;
	protected $discussion;

	public function created()
	{
		$this->discussionId = $this->parameter('discussion_id');
		$this->discussion = Discussion::find($this->discussionId);

		$this->channelId = $this->store('channel_id') ?: $this->discussion?->channel_id;
		$this->channel = Channel::find($this->channelId);

		// authorize() only guards submit/ajax in Kompo; this also guards the GET render
		if ($this->channel && !auth()->user()->can('view', $this->channel)) {
			abort(403);
		}
	}

	public function authorize()
	{
		return $this->channel && auth()->user()->can('view', $this->channel);
	}

	public function beforeSave()
	{
		$this->model->channel_id = $this->channelId;
		$this->model->discussion_id = $this->discussionId;
		$this->model->setSummaryFrom(request('html'));
	}

	public function afterSave()
	{
		if ($this->model->discussion_id) {
			Discussion::findOrFail($this->model->discussion_id)->reopenForAllUsers();
		}
	}

	public function completed()
	{
		$this->model->broadcastSent();
	}

	/**
	 * Background-send handler (kit selfPost): persists the message and returns the
	 * rendered bubble, which replaces the optimistic temp item in the messages panel.
	 */
	protected function persistChatMessage(array $payload)
	{
		$html = $payload['html'] ?? null;

		abort_if(!$html || !trim(strip_tags($html)), 422, __('discussions.message-required'));

		$discussion = new Discussion();
		$discussion->channel_id = $this->channelId;
		$discussion->discussion_id = $this->discussionId;
		// $discussion->subject = $this->discussionId ? null : ($payload['subject'] ?: null);
		$discussion->html = $html;
		$discussion->setSummaryFrom($html);
		$discussion->save();

		if ($discussion->discussion_id) {
			Discussion::findOrFail($discussion->discussion_id)->reopenForAllUsers();
		}

		$discussion->broadcastSent();

		// Own bubble, no avatar, no entrance animation: the optimistic temp bubble
		// already animated in; the swap to the persisted card should be seamless
		return $discussion->cardWithActions(false, false);
	}

	/* TYPING INDICATOR (whispers on the already-authorized team channel) */

	protected function typingWhisperChannel(): ?string
	{
		return $this->channel ? 'discussion.'.$this->channel->team_id : null;
	}

	protected function typingWhisperEvent(): string
	{
		// Scoped per discussion channel so typing in one channel doesn't show in others
		return 'typing.'.$this->channelId;
	}

	/* COMPOSER (chat kit) - enter-to-send and the optimistic bubble come from the
	   kit's shared wiring (ChatScripts::initComposer inside the parent render()) */

	protected function composerInput()
	{
		// Short debounce: commits the value to the form model quickly so the classic
		// (attachment) submit serializes current text; background sends read the
		// editor directly and don't depend on it
		return _CKEditor()->name('html')
			->class('chat-composer-input mb-0 flex-1 min-w-0')
			->debounce(100)
			->focusOnLoad();
	}

	protected function panelSelector(): string
	{
		// Replies refresh the thread card; top-level messages refresh the channel panel
		return $this->discussionId ?
			'#discussion-card-'.$this->discussionId :
			'#channel-discussion-panel';
	}

	protected function composerId(): string
	{
		// Kept verbatim: SingleDiscussionCard's reply button scrolls to this id
		return 'discussion-ckeditor'.$this->discussionId;
	}

	protected function topSlot()
	{
		return $this->statusLink();
	}

	protected function attachmentElement()
	{
		return _MultiFile()->name('files')
			->class('chat-composer-upload mb-0 shrink-0')
			->extraAttributes([
				'team_id' => currentTeam()->id,
			]);
	}

	protected function sendButtonLabel(): string
	{
		return 'discussions.send';
	}

	protected function requireContentSelectors(): array
	{
		// File chips count as content so attachment-only sends pass the empty-send guard
		return ['.chat-composer-upload .vlCustomLabel', '.discussion-composer-upload .vlCustomLabel'];
	}

	protected function optimisticEnabled(): bool
	{
		// Replies render inside a SingleDiscussionCard with its own items root; they
		// keep the plain (non-optimistic) send
		return !$this->discussionId;
	}

	protected function optimisticBubbleHtml(): string
	{
		// Kit own-bubble markup with the ck-content classes so the temp bubble is
		// visually identical to the persisted one rendered by cardWithActions()
		return (new ChatBubbleRenderer())->ownBubbleTemplate(auth()->user()->name, null, 'ck ck-content leading-relaxed');
	}

	protected function statusLink()
	{
		// return $this->discussionId ?

		// 	_Html(__('discussions.replying-to').' -> '.$this->discussion->subject)
		// 		->class('text-xs text-gray-500 mb-1 px-1') :

		// 	_Input()->placeholder('discussions.subject-optional')->name('subject')
		// 		->class('discussion-subject-input mb-2')
		// 		->dontSubmitOnEnter();
	}

	public function rules()
	{
		return [
			'html' => 'required_without:files'
		];
	}
}
