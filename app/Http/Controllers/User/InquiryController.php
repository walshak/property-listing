<?php

namespace App\Http\Controllers\User;

use App\Models\Inquiry;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Models\PlanSubscribe;
use App\Http\Controllers\Controller;
use App\Models\InquiryReply;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class InquiryController extends Controller
{

    public function inquiry(Request $request)
    {

        $request->validate([
            'inquiry' => 'required',
        ]);

        $property = Property::find($request->id);

        $agent = User::find($property->user_id);

        $user = User::find(Auth::id());

        //check if agent has active plan
        $agent_subscription = PlanSubscribe::where('user_id', $agent->id)->orderBy('created_at', 'desc')->first();
        if ($agent_subscription) {
            if ($agent_subscription->expire_date < now() || $agent_subscription->inquiries_left < 1) {
                $notify[] = ['error', 'Opps! You can not send inquiries to this agent.'];
                return back()->withNotify($notify);
            }
        } else {
            $notify[] = ['error', 'Opps! You can not send inquiries to this agent.'];
            return back()->withNotify($notify);
        }

        //check if user has active plan
        $user_subscription = PlanSubscribe::where('user_id', $user->id)->orderBy('created_at', 'desc')->first();
        if ($user_subscription) {
            if ($user_subscription->expire_date < now() || $user_subscription->inquiries_left < 1) {
                $notify[] = ['error', 'Opps! You can not send inquiries, You are over your limit. Please subscribe to a plan.'];
                return back()->withNotify($notify);
            }
        } else {
            $notify[] = ['error', 'Opps! You can not send inquiries,, You are over your limit. Please subscribe to a plan.'];
            return back()->withNotify($notify);
        }


        $inquiry                    =   new Inquiry();
        $inquiry->inquiry           =   $request->inquiry;
        $inquiry->user_id           =   $user->id;
        $inquiry->property_id       =   $request->id;
        $inquiry->save();

        //subtarct from their available enquiries
        $agent_subscription->inquiries_left = $agent_subscription->inquiries_left - 1;
        $agent_subscription->save();

        $user_subscription->inquiries_left = $user_subscription->inquiries_left - 1;
        $user_subscription->save();


        $notify[] = ['success', 'Your inquiries send successfully'];
        return back()->withNotify($notify);
    }

    public function inquiries()
    {
        $pageTitle = 'Property Queries';

        $user_id = auth()->user()->id;

        $inquiries = Inquiry::whereHas('property', function ($q) use ($user_id) {
            $q->where('user_id', $user_id);
        })->with('property')->paginate(getPaginate(20));

        return view($this->activeTemplate . 'inquiry.list', compact('inquiries', 'pageTitle'));
    }

    public function myQuiriesList()
    {
        $pageTitle = 'My Queries';
        $user_id = auth()->user()->id;
        $myinquiries = Inquiry::where('user_id', $user_id)->with('property')->get();
        return view($this->activeTemplate . 'inquiry.myinquiries_list', compact('myinquiries', 'pageTitle'));
    }

    public function inquiryReplies($id)
    {
        $pageTitle = 'Inquiry Replies';
        $inquiries = Inquiry::findOrFail($id);

        $replies = InquiryReply::where('inquiry_id', $inquiries->id)->with('user')->orderBy('id', 'desc')->paginate(getPaginate(20));

        return view($this->activeTemplate . 'inquiry.replies', compact('replies', 'inquiries', 'pageTitle'));
    }


    public function storeReplies(Request $request, $id)
    {

        $request->validate([
            'message' => 'required',
        ]);

        $inquiries_id = $request->id;
        $user_id = auth()->user()->id;

        $inquiryReplies = new InquiryReply();
        $inquiryReplies->inquiry_id = $inquiries_id;
        $inquiryReplies->user_id = $user_id;
        $inquiryReplies->message = $request->message;
        $inquiryReplies->save();

        $notify[] = ['success', 'Your inquiries repliy send successfully'];
        return back()->withNotify($notify);
    }
}
