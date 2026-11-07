<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListMessagesRequest;
use App\Http\Requests\SaveMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messages) {}

    /**
     * GET /api/messages — latest messages, 10 per page, optionally filtered by author.
     */
    public function index(ListMessagesRequest $request): AnonymousResourceCollection
    {
        return MessageResource::collection($this->messages->paginate($request->userIds()));
    }

    /**
     * POST /api/messages — send a message and broadcast it in real time.
     */
    public function store(SaveMessageRequest $request, #[CurrentUser] User $user): MessageResource
    {
        $message = $this->messages->send($user, $request->messageContent());

        return new MessageResource($message->load('user'));
    }

    /**
     * PATCH /api/messages/{message} — authors only (MessagePolicy).
     */
    #[Authorize('update', 'message')]
    public function update(SaveMessageRequest $request, Message $message): MessageResource
    {
        $message = $this->messages->update($message, $request->messageContent());

        return new MessageResource($message->load('user'));
    }

    /**
     * DELETE /api/messages/{message} — soft delete, authors only (MessagePolicy).
     */
    #[Authorize('delete', 'message')]
    public function destroy(Message $message): Response
    {
        $this->messages->delete($message);

        return response()->noContent();
    }
}
