<?php

namespace Illuminate\Tests\Pipeline;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Pipeline\Pipeline;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

class PipelineTest extends TestCase
{
    public function testPipelineBasicUsage()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline(new Container))
                    ->send('foo')
                    ->through([PipelineTestPipeOne::class, $pipeTwo])
                    ->then(function ($piped) {
                        return $piped;
                    });

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        $this->assertSame('foo', $_SERVER['__test.pipe.two']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two']);
    }

    public function testPipelineUsageWithObjects()
    {
        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([new PipelineTestPipeOne])
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithInvokableObjects()
    {
        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([new PipelineTestPipeTwo])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithCallable()
    {
        $function = function ($piped, $next) {
            $_SERVER['__test.pipe.one'] = 'foo';

            return $next($piped);
        };

        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([$function])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);

        $result = (new Pipeline(new Container))
            ->send('bar')
            ->through($function)
            ->thenReturn();

        $this->assertSame('bar', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithPipe()
    {
        $object = new stdClass();

        $object->value = 0;

        $function = function ($object, $next) {
            $object->value++;

            return $next($object);
        };

        $result = (new Pipeline(new Container))
            ->send($object)
            ->through([$function])
            ->pipe([$function])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame($object, $result);
        $this->assertEquals(2, $object->value);
    }

    public function testPipelineUsageWithInvokableClass()
    {
        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([PipelineTestPipeTwo::class])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testThenMethodIsNotCalledIfThePipeReturns()
    {
        $_SERVER['__test.pipe.then'] = '(*_*)';
        $_SERVER['__test.pipe.second'] = '(*_*)';

        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([
                fn ($value, $next) => 'm(-_-)m',
                fn ($value, $next) => $_SERVER['__test.pipe.second'] = 'm(-_-)m',
            ])
            ->then(function ($piped) {
                $_SERVER['__test.pipe.then'] = '(0_0)';

                return $piped;
            });

        $this->assertSame('m(-_-)m', $result);
        // The then callback is not called.
        $this->assertSame('(*_*)', $_SERVER['__test.pipe.then']);
        // The second pipe is not called.
        $this->assertSame('(*_*)', $_SERVER['__test.pipe.second']);

        unset($_SERVER['__test.pipe.then']);
    }

    public function testThenMethodInputValue()
    {
        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([function ($value, $next) {
                $value = $next('::not_foo::');

                $_SERVER['__test.pipe.return'] = $value;

                return 'pipe::'.$value;
            }])
            ->then(function ($piped) {
                $_SERVER['__test.then.arg'] = $piped;

                return 'then'.$piped;
            });

        $this->assertSame('pipe::then::not_foo::', $result);
        $this->assertSame('::not_foo::', $_SERVER['__test.then.arg']);

        unset($_SERVER['__test.then.arg']);
        unset($_SERVER['__test.pipe.return']);
    }

    public function testPipelineUsageWithParameters()
    {
        $parameters = ['one', 'two'];

        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through(PipelineTestParameterPipe::class.':'.implode(',', $parameters))
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertEquals($parameters, $_SERVER['__test.pipe.parameters']);

        unset($_SERVER['__test.pipe.parameters']);
    }

    public function testPipelineViaChangesTheMethodBeingCalledOnThePipes()
    {
        $pipelineInstance = new Pipeline(new Container);
        $result = $pipelineInstance->send('data')
            ->through(PipelineTestPipeOne::class)
            ->via('differentMethod')
            ->then(function ($piped) {
                return $piped;
            });
        $this->assertSame('data', $result);
    }

    public function testPipelineThrowsExceptionOnResolveWithoutContainer()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A container instance has not been passed to the Pipeline.');

        (new Pipeline)->send('data')
            ->through(PipelineTestPipeOne::class)
            ->then(function ($piped) {
                return $piped;
            });
    }

    public function testPipelineThenReturnMethodRunsPipelineThenReturnsPassable()
    {
        $result = (new Pipeline(new Container))
                    ->send('foo')
                    ->through([PipelineTestPipeOne::class])
                    ->thenReturn();

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineConditionable()
    {
        $result = (new Pipeline(new Container))
            ->send('foo')
            ->when(true, function (Pipeline $pipeline) {
                $pipeline->pipe([PipelineTestPipeOne::class]);
            })
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        unset($_SERVER['__test.pipe.one']);

        $_SERVER['__test.pipe.one'] = null;
        $result = (new Pipeline(new Container))
            ->send('foo')
            ->when(false, function (Pipeline $pipeline) {
                $pipeline->pipe([PipelineTestPipeOne::class]);
            })
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertNull($_SERVER['__test.pipe.one']);
        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineFinally()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            $next($piped);
        };

        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->finally(function ($piped) {
                $_SERVER['__test.pipe.finally'] = $piped;
            })
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame(null, $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        $this->assertSame('foo', $_SERVER['__test.pipe.two']);
        $this->assertSame('foo', $_SERVER['__test.pipe.finally']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two'], $_SERVER['__test.pipe.finally']);
    }

    public function testPipelineFinallyMethodWhenChainIsStopped()
    {
        $pipeTwo = function ($piped) {
            $_SERVER['__test.pipe.two'] = $piped;
        };

        $result = (new Pipeline(new Container))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->finally(function ($piped) {
                $_SERVER['__test.pipe.finally'] = $piped;
            })
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame(null, $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        $this->assertSame('foo', $_SERVER['__test.pipe.two']);
        $this->assertSame('foo', $_SERVER['__test.pipe.finally']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two'], $_SERVER['__test.pipe.finally']);
    }

    public function testPipelineFinallyOrder()
    {
        $std = new stdClass();

        $result = (new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
                function ($std, $next) {
                    $std->value++;

                    return $next($std);
                },
            ])->finally(function ($std) {
                $this->assertSame(3, $std->value);

                $std->value++;
            })->then(function ($std) {
                $std->value++;

                return $std;
            });

        $this->assertSame(4, $std->value);
        $this->assertSame(4, $result->value);
    }

    public function testPipelineFinallyOutOfOrder()
    {
        $std = new stdClass;

        ($pipeline = new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 100;

                    return $next($std);
                },
                function ($std, $next) {
                    $std->value = 200;

                    return $next($std);
                },
            ])->then(function ($std) use (&$pipeline) {
                $std->value = 300;

                return $pipeline;
            })->finally(function () {
                $this->fail('Finally should not be called');
            });

        $this->assertSame(300, $std->value);
    }

    public function testPipelineFinallyWhenExceptionOccurs()
    {
        $std = new stdClass();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('My Exception: 1');

        try {
            (new Pipeline(new Container))
                ->send($std)
                ->through([
                    function ($std, $next) {
                        $std->value = 1;

                        return $next($std);
                    },
                    function ($std) {
                        throw new Exception('My Exception: '.$std->value);
                    },
                ])->finally(function ($std) {
                    $this->assertSame(1, $std->value);

                    $std->value++;
                })->then(function ($std) {
                    $std->value = 0;

                    return $std;
                });
        } catch (Exception $e) {
            $this->assertSame('My Exception: 1', $e->getMessage());
            $this->assertSame(2, $std->value);

            throw $e;
        }
    }

    public function testPipelineCatch()
    {
        $std = new stdClass;

        (new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
                function ($std) {
                    throw new Exception('My Exception: '.$std->value);
                },
                function ($std, $next) {
                    $std->value++;

                    return $next($std);
                },
            ])->catch(function ($std, Throwable $e) {
                $std->value = 100;
                $this->assertSame('My Exception: 1', $e->getMessage());
            })
            ->then(function ($std) {
                $this->assertSame(1, $std->value);

                return $std;
            });

        $this->assertSame(100, $std->value);
    }

    public function testPipelineCatchWhenThereIsNoException()
    {
        $std = new stdClass;

        (new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
                function ($std, $next) {
                    $std->value++;

                    return $next($std);
                },
            ])->catch(function () {
                $this->fail('Catch should not be called');
            })
            ->then(function ($std) {
                $this->assertSame(2, $std->value);

                return $std;
            });
    }

    public function testPipelineCatchAndFinallyWhenThereIsException()
    {
        $std = new stdClass;

        (new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
                function ($std) {
                    $std->value++;
                    throw new Exception('My Exception: '.$std->value);
                },
            ])->finally(function ($std) {
                $this->assertSame(100, $std->value);

                $std->value = 200;
            })->catch(function ($std, Throwable $e) {
                $std->value = 100;

                $this->assertSame('My Exception: 2', $e->getMessage());
            })->then(function ($std) {
                $this->assertSame(2, $std->value);

                return $std;
            });

        $this->assertSame(200, $std->value);
    }

    public function testPipelineCatchAndFinallyWhenThereIsNoException()
    {
        $std = new stdClass;

        (new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
            ])->catch(function () {
                $this->fail('Catch should not be called');
            })
            ->finally(function ($std) {
                $this->assertSame(200, $std->value);
                $std->value = 300;
            })
            ->then(function ($std) {
                $this->assertSame(1, $std->value);
                $std->value = 200;

                return $std;
            });

        $this->assertSame(300, $std->value);
    }

    public function testPipelineCatchOutOfOrder()
    {
        $std = new stdClass;

        try {
            (new Pipeline(new Container))
                ->send($std)
                ->through([
                    function ($std, $next) {
                        $std->value = 1;

                        return $next($std);
                    },
                    function ($std) {
                        $std->value++;
                        throw new Exception('My Exception: '.$std->value);
                    },
                ])->then(function () {
                    $this->fail('Then should not be called');
                })->catch(function () {
                    $this->fail('Catch should not be called');
                });
        } catch (Throwable $e) {
            $this->assertSame('My Exception: 2', $e->getMessage());
        }

        $this->assertSame(2, $std->value);
    }

    public function testPipelineCatchWithReturn()
    {
        $std = new stdClass;

        $result = (new Pipeline(new Container))
            ->send($std)
            ->through([
                function ($std) {
                    $std->value = 1;
                    throw new Exception('My Exception: '.$std->value);
                },
            ])->catch(function ($std, Throwable $e) {
                $this->assertSame(1, $std->value);
                $std->value = 100;
                $this->assertSame('My Exception: 1', $e->getMessage());

                return 1000;
            })
            ->then(function ($std) {
                $this->assertSame(1, $std->value);
                $std->value = 200;

                return $std;
            });

        $this->assertSame(100, $std->value);
        $this->assertSame(1000, $result);
    }
}

class PipelineTestPipeOne
{
    public function handle($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }

    public function differentMethod($piped, $next)
    {
        return $next($piped);
    }
}

class PipelineTestPipeTwo
{
    public function __invoke($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }
}

class PipelineTestParameterPipe
{
    public function handle($piped, $next, $parameter1 = null, $parameter2 = null)
    {
        $_SERVER['__test.pipe.parameters'] = [$parameter1, $parameter2];

        return $next($piped);
    }
}
