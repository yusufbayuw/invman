<?php

namespace App\Http\Controllers\Chatify;

use App\Models\ChMessage as Message;
use App\Models\User;
use Chatify\Facades\ChatifyMessenger as Chatify;
use Chatify\Http\Controllers\MessagesController as BaseMessagesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class MessagesController extends BaseMessagesController
{
    /**
     * Return contacts ordered by their latest conversation activity.
     *
     * Chatify's original query selects users.* while grouping only by users.id,
     * which is rejected by MySQL when ONLY_FULL_GROUP_BY is enabled.
     */
    public function getContacts(Request $request): JsonResponse
    {
        $authId = Auth::id();
        $contactIdExpression = 'CASE WHEN ch_messages.from_id = ? THEN ch_messages.to_id ELSE ch_messages.from_id END';

        $latestConversations = Message::query()
            ->selectRaw("{$contactIdExpression} AS contact_id", [$authId])
            ->selectRaw('MAX(ch_messages.created_at) AS max_created_at')
            ->where(function ($query) use ($authId): void {
                $query->where('ch_messages.from_id', $authId)
                    ->orWhere('ch_messages.to_id', $authId);
            })
            ->groupByRaw($contactIdExpression, [$authId]);

        $users = User::query()
            ->joinSub($latestConversations, 'latest_conversations', function ($join): void {
                $join->on('users.id', '=', 'latest_conversations.contact_id');
            })
            ->select('users.*')
            ->orderByDesc('latest_conversations.max_created_at')
            ->paginate($request->integer('per_page') ?: $this->perPage);

        $contacts = collect($users->items())
            ->map(fn (User $user): string => Chatify::getContactItem($user))
            ->implode('');

        if ($contacts === '') {
            $contacts = '<p class="message-hint center-el"><span>Your contact list is empty</span></p>';
        }

        return Response::json([
            'contacts' => $contacts,
            'total' => $users->total(),
            'last_page' => $users->lastPage(),
        ]);
    }
}
