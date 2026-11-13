<?php

namespace App\Http\Controllers;

use App\Support\Funding;

class FundController extends Controller
{
    public function show()
    {
        $c = Funding::active();
        if (! $c) {
            return view('fund.show', ['campaign' => null]);
        }
        $raised = Funding::raised($c);

        return view('fund.show', [
            'campaign'  => $c,
            'heads'     => $c->heads()->get(),
            'raised'    => $raised,
            'percent'   => Funding::percent($raised, $c->goal_amount),
            'canDonate' => Funding::canDonate($c, $raised),
            'donors'    => Funding::publicDonors($c),
        ]);
    }
}
