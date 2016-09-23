<?php

namespace Oro\Bundle\WebsiteSearchBundle\Tests\Unit\Event;

use Oro\Bundle\WebsiteSearchBundle\Event\ReindexationRequestEvent;

class ReindexationRequestEventTest extends \PHPUnit_Framework_TestCase
{
    public function testInitialization()
    {
        $reindexationEvent = new ReindexationRequestEvent(
            self::class,
            1024,
            [1, 2, 3],
            true
        );

        $this->assertEquals(self::class, $reindexationEvent->getClassName());
        $this->assertEquals(1024, $reindexationEvent->getWebsiteId());
        $this->assertSame([1, 2, 3], $reindexationEvent->getIds());
        $this->assertTrue($reindexationEvent->isScheduled());
    }
}
