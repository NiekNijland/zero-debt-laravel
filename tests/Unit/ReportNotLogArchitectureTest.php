<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\ReportNotLogGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('severe logs and tenant ids in a log context', [
    'imported facade' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable \$e) { Log::error('failed', ['exception' => \$e]); }\n", 'warning or higher that handles a caught exception'],
    'global alias' => ["<?php\nnamespace App;\ntry { f(); } catch (\\RuntimeException \$e) { \\Log::error('failed'); }\n", 'warning or higher that handles a caught exception'],
    'a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::channel('daily')->error('failed'); }\n", 'report the exception with report()'],
    'nested in an if' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable \$e) { if (\$e->getCode() > 0) { Log::error('failed'); } }\n", 'warning or higher'],
    'a warning for an exact exception' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\App\\RefusedException) { Log::warning('refused'); }\n", 'warning or higher'],
    'a critical on a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::channel('daily')->critical('failed'); }\n", 'warning or higher'],
    'an alert' => ["<?php\nnamespace App;\ntry { f(); } catch (\\Throwable) { \\Log::alert('failed'); }\n", 'warning or higher'],
    'an emergency' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::emergency('failed'); }\n", 'warning or higher'],
    'a tenant id in an info context' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::info('done', ['tenant_id' => \$tenant->id]);\n", 'a tenant_id key written into a log context by hand'],
    'a tenant id in a warning in a catch' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable) { Log::warning('skipped', ['order_id' => 1, 'tenant_id' => 2]); }\n", 'the log context adds the tenant, leave the key out'],
    'a tenant id on a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::channel('daily')->notice('done', ['tenant_id' => 2]);\n", 'leave the key out'],
]);

it('detects a severe log that handles a caught exception and a tenant id written into a log context', function (string $source, string $words): void {
    $violations = new ReportNotLogGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->not->toBe([])
        ->and($violations[0])->toStartWith('line ')
        ->and(implode("\n", $violations))->toContain($words);
})->with('severe logs and tenant ids in a log context');

dataset('logs that are not violations', [
    'a report of a wrapped exception' => ["<?php\nnamespace App;\ntry { f(); } catch (\\Throwable \$e) { report(DomainException::wrap(\$e)); }\n"],
    'an info line in a catch' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\App\\RefusedException) { Log::info('refused', ['user_id' => 1]); }\n"],
    'a debug line on a channel in a catch' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\App\\RetriedException) { Log::channel('daily')->debug('retried'); }\n"],
    'an error inside a closure that the catch only stores' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable \$e) { \$later = static function (): void { Log::error('not here'); }; throw \$e; }\n"],
    'an error outside a catch' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::error('a state that is not an exception', ['order_id' => 1]);\n"],
    'a tenant id in a query, not a log' => ["<?php\nnamespace App;\nOrder::query()->where('tenant_id', 1)->update(['tenant_id' => 2]);\n"],
    'another id in a log context' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::info('moved', ['supplier_id' => 2]);\n"],
    'a logger class named Log in another namespace' => ["<?php\nnamespace App;\nuse App\\Support\\Log;\ntry { f(); } catch (\\Throwable) { Log::error('x', ['tenant_id' => 1]); }\n"],
]);

it('accepts a report, an info or debug line in a catch, an error outside a catch and other keys', function (string $source): void {
    expect(new ReportNotLogGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
})->with('logs that are not violations');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Job.php' => "<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (\\Throwable \$e) { Log::error('failed'); }\n",
        'app/Fine.php' => "<?php\nnamespace App;\ntry { f(); } catch (\\Throwable \$e) { report(\$e); }\n",
    ], prefix: '.checkout-');

    try {
        $violations = new ReportNotLogGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Job.php: line 4: a log call of level warning or higher');
});

it('reports caught exceptions instead of logging them, and leaves the tenant id to the log context', function (): void {
    expect(new ReportNotLogGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
