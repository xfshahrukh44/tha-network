<?php

namespace App\Http\Controllers;

use App\Helpers\WebResponses;
use App\Models\SendInvitation;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvitationCode extends Controller
{
    public function showInvitationCodeForm()
    {
        return Inertia::render('Auth/SignUpInvitation');
    }

    public function sendInvitationCode(Request $request)
    {
//        dd($request->all());
        $data = $request->validate([
            'email' => [
                'required_if:email,in:send_code_type',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'phone' => [
                'required_if:phone,in:send_code_type',
                'nullable',
                'string',
                'max:255'
            ],
            'send_code_type' => [
                'required',
                'in:email,phone'
            ],
        ]);
        try {
            $code = $this->generateUniqueCode();
            if ($code instanceof \Exception)
                throw $code;

            DB::beginTransaction();
            $sendInvitation = SendInvitation::firstOrNew([
                'email' => $data['email']
            ]);
            $sendInvitation->phone = $data['phone'];
            $sendInvitation->save();

            $sendInvitation->invitation()->forceDelete();
            $sendInvitation->invitation()->create([
                'code' => $code
            ]);
            DB::commit();

            /* Mail::send(
                 'mails.send-invitation-code-mail',
                 ['code' => $code],
                 function ($message) use ($data) {
                     $message->to($data['email'])->subject('Invitation Code!');
                 }
             );*/

            if (!$this->mailCode($data['email'], 'Tha Network - Invitation Code!', $code))
                return WebResponses::exception("Email not send!");

            $route = route('loginForm', 'send-code=success');
            session()->put('send-code', 'success');
            return WebResponses::success(
                'Request submitted successfully!',
                null,
                $route
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return WebResponses::exception($e->getMessage());
        }
    }

    public function verifyCode(Request $request)
    {
        $userInvitation = null;
        $request->validate([
            'code' => [
                'required',
                'string',
                'size:6',
                function ($attribute, $value, $fail) use (&$userInvitation) {
                    if (!$value) return;
                    $userInvitation = UserInvitation::where('code', $value)
                        ->whereDoesntHave('payment')
                        ->whereNull('deleted_at')
                        ->first();
                    if (is_null($userInvitation))
                        $fail("Invalid code!");
                },
            ],
        ]);
        try {
            session()->put('validate-code', $userInvitation->id);

            return WebResponses::success(
                null,
                null,
                route('howItWorks')
            );
        } catch (\Exception $e) {
            return WebResponses::exception($e->getMessage());
        }
    }

    public function generateUniqueCode()
    {
        try {
            do {
                $code = random_int(100000, 999999);
            } while (UserInvitation::where("code", "=", $code)->first());

            return $code;
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $to
     * @param $subject
     * @param $code
     * @return bool
     */
    public function mailCode($to, $subject, $code)
    {
        $from = 'no-reply@tha-network.com';

        // To send HTML mail, the Content-type header must be set
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

        // Create email headers
        $headers .= 'From: ' . $from . "\r\n" .
            'Reply-To: ' . $from . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // Compose a simple HTML email message
        $message = '<html><body>';
        $message .= '<h1 style="color:#f40;">Dear User!</h1>';
        $message .= '<p style="color:#080;font-size:18px;">Your generated invitation code: ' . $code . '</p>';
        $message .= 'Link: <a href="'.route('loginForm', ['send_code' => 'success']).'">'.route('loginForm', ['send_code' => 'success']).'</a>';
        $message .= '</body></html>';

        // Sending email
        if (mail($to, $subject, $message, $headers)) {
            return true;
        } else {
            return false;
        }
    }
}
