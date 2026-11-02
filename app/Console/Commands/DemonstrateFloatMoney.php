<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * `php artisan pos:money` — the on-camera demo for episode 16.
 *
 * Every figure printed here is real PHP output. Note in particular that PHP's
 * round() copes fine with 1.005 — the scare story other tutorials repeat is
 * simply false, and we say so. The genuine damage is done by comparison,
 * division and serialisation, not by rounding.
 */
class DemonstrateFloatMoney extends Command
{
    protected $signature = 'pos:money';

    protected $description = 'Show where float money actually breaks (and where it does not)';

    public function handle(): int
    {
        $this->part1RepresentationIsApproximate();
        $this->part2TheExactPaymentBug();
        $this->part3DividingMoney();
        $this->part4TheWireFormat();
        $this->part5TheMythWeWillNotRepeat();

        return self::SUCCESS;
    }

    private function part1RepresentationIsApproximate(): void
    {
        $this->newLine();
        $this->components->info('1. A float is an approximation, not your number');
        $this->newLine();

        $this->line('  0.1 + 0.2 to 20 places   '.sprintf('%.20f', 0.1 + 0.2));
        $this->line('  0.1 + 0.2 === 0.3        '.var_export(0.1 + 0.2 === 0.3, true));
        $this->newLine();
    }

    private function part2TheExactPaymentBug(): void
    {
        $this->components->info('2. The bug that reaches a customer');
        $this->newLine();

        // A basket of 0.10 + 0.20. The customer hands over exactly 0.30.
        $due = 0.10 + 0.20;
        $paid = 0.30;

        $this->components->twoColumnDetail('Total due', sprintf('%.2f', $due));
        $this->components->twoColumnDetail('Customer paid', sprintf('%.2f', $paid));
        $this->components->twoColumnDetail('paid >= due ?', var_export($paid >= $due, true));
        $this->components->twoColumnDetail('Shortfall', sprintf('%.20f', $due - $paid));
        $this->newLine();

        $this->components->error(
            'The customer paid the exact amount and the till says they are short. '
            .'Both numbers display as 0.30.'
        );
        $this->newLine();

        // Integer cents: no such problem.
        $dueCents = 10 + 20;
        $paidCents = 30;

        $this->components->info('   Same thing in integer cents:');
        $this->line('     due = '.$dueCents.'   paid = '.$paidCents.'   paid >= due ? '.var_export($paidCents >= $dueCents, true));
        $this->newLine();
    }

    private function part3DividingMoney(): void
    {
        $this->components->info('3. Money does not always divide evenly');
        $this->newLine();

        $this->line('  0.30 / 3 = '.sprintf('%.20f', 0.30 / 3));
        $this->newLine();

        // Splitting 100 cents three ways, losing nothing.
        $totalCents = 100;
        $ways = 3;
        $base = intdiv($totalCents, $ways);
        $remainder = $totalCents % $ways;

        $shares = array_fill(0, $ways, $base);
        for ($i = 0; $i < $remainder; $i++) {
            $shares[$i]++;   // hand the spare pennies out, one each
        }

        $this->line('  Splitting 1.00 three ways in cents: '.implode(' + ', $shares).' = '.array_sum($shares));
        $this->line('  Nothing is lost, and you decide who gets the spare penny.');
        $this->newLine();
    }

    private function part4TheWireFormat(): void
    {
        $this->components->info('4. What reaches the client');
        $this->newLine();

        $this->line('  json_encode(12.50)    '.json_encode(12.50).'          <- trailing zero gone');
        $this->line('  json_encode(0.1 + 0.2) '.json_encode(0.1 + 0.2).'  <- the error travels');
        $this->line('  json_encode("12.50")  '.json_encode('12.50').'      <- exact, every time');
        $this->newLine();
    }

    private function part5TheMythWeWillNotRepeat(): void
    {
        $this->components->info('5. A myth worth correcting');
        $this->newLine();

        $this->line('  Many tutorials claim round(1.005, 2) gives 1.00 in PHP. Check it:');
        $this->newLine();

        foreach ([1.005, 2.675, 8.475] as $v) {
            $this->line(sprintf('    round(%.3f, 2) = %-6s  (stored as %.20f)', $v, round($v, 2), $v));
        }

        $this->newLine();
        $this->components->warn(
            'PHP rounds these correctly — it compensates for the representation error. '
            .'The value really is stored low, but round() is not where floats hurt you. '
            .'Comparison, division and serialisation are.'
        );
        $this->newLine();
    }
}
