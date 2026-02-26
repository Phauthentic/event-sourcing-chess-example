<?php

declare(strict_types=1);

namespace App\Tests\Repository\EventPublisher;

use Phauthentic\EventSourcing\Projection\ProjectorInterface;
use Phauthentic\EventSourcing\Repository\EventPublisher\SynchronousProjectorMiddleware;
use PHPUnit\Framework\TestCase;

class SynchronousProjectorMiddlewareTest extends TestCase
{
    private SynchronousProjectorMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new SynchronousProjectorMiddleware();
    }

    public function testIsNotInterrupting(): void
    {
        $this->assertFalse($this->middleware->isInterrupting());
    }

    public function testHandlesEventWithNoProjectors(): void
    {
        $event = new \stdClass();

        // Should not throw any exceptions
        $this->middleware->handle($event);
    }

    public function testHandlesEventWithProjectors(): void
    {
        $event = new \stdClass();

        $projector1 = $this->createMock(ProjectorInterface::class);
        $projector1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);
        $projector1->expects($this->once())
            ->method('project')
            ->with($event);

        $projector2 = $this->createMock(ProjectorInterface::class);
        $projector2->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(false);
        $projector2->expects($this->never())
            ->method('project');

        $this->middleware->addProjector($projector1);
        $this->middleware->addProjector($projector2);

        $this->middleware->handle($event);
    }

    public function testFailsFastOnProjectorError(): void
    {
        $event = new \stdClass();

        $projector1 = $this->createMock(ProjectorInterface::class);
        $projector1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);
        $projector1->expects($this->once())
            ->method('project')
            ->with($event)
            ->willThrowException(new \RuntimeException('Projection failed'));

        $projector2 = $this->createMock(ProjectorInterface::class);
        $projector2->expects($this->never())
            ->method('supports');
        $projector2->expects($this->never())
            ->method('project');

        $middleware = new SynchronousProjectorMiddleware([], true); // failFast = true
        $middleware->addProjector($projector1);
        $middleware->addProjector($projector2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Projection failed');

        $middleware->handle($event);
    }

    public function testContinuesOnProjectorErrorWhenNotFailingFast(): void
    {
        $event = new \stdClass();

        $projector1 = $this->createMock(ProjectorInterface::class);
        $projector1->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);
        $projector1->expects($this->once())
            ->method('project')
            ->with($event)
            ->willThrowException(new \RuntimeException('Projection failed'));

        $projector2 = $this->createMock(ProjectorInterface::class);
        $projector2->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);
        $projector2->expects($this->once())
            ->method('project')
            ->with($event);

        $middleware = new SynchronousProjectorMiddleware([], false); // failFast = false
        $middleware->addProjector($projector1);
        $middleware->addProjector($projector2);

        // Should not throw exception
        $middleware->handle($event);
    }

    public function testConstructorAcceptsProjectors(): void
    {
        $projector = $this->createMock(ProjectorInterface::class);

        $middleware = new SynchronousProjectorMiddleware([$projector]);

        $event = new \stdClass();
        $projector->expects($this->once())
            ->method('supports')
            ->with($event)
            ->willReturn(true);
        $projector->expects($this->once())
            ->method('project')
            ->with($event);

        $middleware->handle($event);
    }
}