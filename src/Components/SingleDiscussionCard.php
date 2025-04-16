<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Components\Traits\ScrollToOnLoadTrait;
use Kompo\Discussions\Models\Discussion;
use Condoedge\Utils\Kompo\Common\Query;

class SingleDiscussionCard extends Query
{
    use ScrollToOnLoadTrait;

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';
    public $itemsWrapperStyle = 'max-height: 320px';

    public $noItemsFound = '';

    public $topPagination = true;
    public $bottomPagination = false;

    public $paginationType = 'Scroll';

    protected $discussionId;

    public function booted()
    {
        $this->activateScroll('#discussion-card-', '.discussion-scroll-'.$this->discussionId, 1100);
    }

    public function created()
    {
        $this->discussionId = $this->store('discussion_id') ?: $this->parameter('id');
        $this->discussion = Discussion::with('addedBy', 'files', 'read')
                                ->find($this->discussionId);

        $this->id('discussion-card-'.$this->discussionId);
        $this->itemsWrapperClass .= ' discussion-scroll-'.$this->discussionId;
    }

    public function query()
    {
        return $this->discussion->discussions()
            ->with('addedBy', 'files', 'read')
            ->orderByDesc('created_at');
    }

    public function left()
    {
        return $this->discussion->profileImg();
    }

    public function top()
    {
        return _Rows(

            !$this->discussion->subject ? null :
                _Link($this->discussion->subject)
                    ->class('pb-2 px-4 mb-2 font-semibold border-b border-gray-200 rounded-none')
                    ->href('discussions', [
                        'channel_id' => $this->discussion->channel_id,
                        'discussion_id' => $this->discussionId
                    ]),

            $this->discussion->cardWithActions(false)->class('pb-2 px-4')

        )->class('pt-2 bg-white rounded-t-xl');
    }

    public function render($discussion)
    {
        $card = $discussion->cardWithActions()->id('discussion-card-'.$discussion->id);

        if (!$discussion->read) {
            $this->scrollToId = $this->scrollToId ?: $discussion->id;
            $discussion->markRead();
        }

        return $card;
    }

    public function bottom()
    {
        return _Panel(
            _Flex(
                _Button('Reply')->icon(_Sax('message',20))
                    ->outlined()
                    ->class('text-xs mb-2 ml-2')
                    ->get('discussion-reply', [
                        'discussion_id' => $this->discussionId
                    ])->inPanel('single-discussion-form'.$this->discussionId)
                    ->scrollTo('#discussion-ckeditor'.$this->discussionId, 300, [
                        'container' => '.channel-scroll',
                        'offset' => -100
                    ], 1000)
            )
        )->id('single-discussion-form'.$this->discussionId)
        ->class('pt-4 pb-2 px-2 bg-white rounded-b-xl')
        ->class('border-b border-gray-200');
    }
}
