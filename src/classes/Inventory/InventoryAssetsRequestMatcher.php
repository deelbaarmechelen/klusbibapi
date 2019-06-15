<?php

namespace Api\Inventory;

use Kevinrob\GuzzleCache\Strategy\Delegate\RequestMatcherInterface;
use Psr\Http\Message\RequestInterface;

class InventoryAssetsRequestMatcher implements RequestMatcherInterface
{

    /**
     * @inheritDoc
     */
    public function matches(RequestInterface $request)
    {
        return false !== strpos($request->getUri()->getPath(), '/api/v1/hardware');
    }
}