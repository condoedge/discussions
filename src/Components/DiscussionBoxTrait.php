<?php

namespace Kompo\Discussions\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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


        $suffix = $this->box == 1 ? $this->suffixArchive : ($this->box == 2 ? $this->suffixTrash : '');

        $this->routeDiscussions .= $suffix;
        $this->routeChannel .= $suffix;
        $this->routeChannelSubjects .= $suffix;
    }

    protected function getBoxFromRoute()
    {
        $routeName = request()->route()->getName();

        return Str::endsWith($routeName, $this->suffixArchive) ? 1 : (
            Str::endsWith($routeName, $this->suffixTrash) ? 2 : null
        );
    }

}
