<?php

namespace Core;

use Silex\Route as BaseRoute;
use Silex\Route\SecurityTrait;

class Route extends BaseRoute
{
    use SecurityTrait;
}