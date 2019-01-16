<?php
/**
 * Service Provider Interface
 * User: marhone
 * Date: 2019/1/16
 * Time: 16:25
 */

namespace Tinyfork\Provider;


use Symfony\Component\DependencyInjection\Container;

interface ServiceProviderInterface
{
    public function register(Container $container);
}