<?php

namespace Kompo\Discussions\Components;

use Illuminate\Support\Str;
use Kompo\Discussions\Models\DiscussionBox;

trait DiscussionBoxTrait
{
    protected $box;

    protected $routeDiscussions;
    protected $routeChannel;
    protected $routeChannelSubjects;

    protected $suffixArchive = '.archive';
    protected $suffixTrash = '.trash';

    protected function initializeBox()
    {
        $this->box = $this->store('box') ?: $this->getBoxFromRoute();

        $this->setRoutes();
    }

    protected function setRoutes()
    {
        $this->routeDiscussions = 'discussions';
        $this->routeChannel = 'channel';
        $this->routeChannelSubjects = 'channel-subjects';

        $suffix = $this->box == DiscussionBox::BOX_ARCHIVE ? $this->suffixArchive :
                    ($this->box == DiscussionBox::BOX_TRASH ? $this->suffixTrash : '');

        $this->routeDiscussions .= $suffix;
        $this->routeChannel .= $suffix;
        $this->routeChannelSubjects .= $suffix;
    }

    protected function getBoxFromRoute()
    {
        $routeName = optional(request()->route())->getName();

        if (!$routeName) {
            return null;
        }

        return Str::endsWith($routeName, $this->suffixArchive) ? DiscussionBox::BOX_ARCHIVE : (
            Str::endsWith($routeName, $this->suffixTrash) ? DiscussionBox::BOX_TRASH : null
        );
    }
}
