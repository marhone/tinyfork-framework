<?php
/**
 * PrivilegeInterface
 * User: marhone
 * Date: 2019/1/8
 * Time: 14:58
 */

namespace Tinyfork\Privilege;


use Tinyfork\Http\Request;

interface PrivilegeInterface
{
    const ACCESS_GRANTED = 1;
    const ACCESS_ABSTAIN = 0;
    const ACCESS_DENIED = -1;

    public function check(Request $request);
}