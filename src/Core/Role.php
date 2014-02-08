<?php

namespace Core;

use Symfony\Component\Security\Core\Role\Role as BaseRole;

class Role extends BaseRole
{
    public function __toString()
    {
        return (string)$this->getRole();
    }
}