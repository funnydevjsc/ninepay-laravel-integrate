<?php

namespace App\Http\Controllers;

use FunnyDev\Ninepay\NinepaySdk;
use Illuminate\Http\Request;

class NinepayController
{
    public function webhook(Request $request)
    {
        $request->validate([
            'result' => 'string|required',
            'checksum' => 'string|required'
        ]);
        $ninepay = new NinepaySdk();
        $result = $ninepay->read_result($request->input('result'), $request->input('checksum'));

        /*
         * You could handle the response of transaction here like:
         * if ($result['status']) {approve order for use or email them...} else {notice them the $result['message']}
         * if $result['message'] is "Trying to fake payment result" then you should block your user!
         * You could get 2 integer variables Session::get('ninepay_hacked') & Session::get('ninepay_failed') to decide what to do with your user.
         */

        return $result;
    }
}