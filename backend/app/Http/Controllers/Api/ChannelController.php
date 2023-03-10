<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Channel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Api\ChannelStoreRequest;
use App\Http\Resources\ChannelResource;


class ChannelController extends Controller
{
    public function __construct(protected Channel $channel)
    {
    }

    public function index(Request $request)
    {
        $channels = Channel::with('users')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return ChannelResource::collection($channels);    
    }


    public function store(ChannelStoreRequest $request)
    {
        $channel = \DB::transaction(function () use ($request) {
            // $channel = Channel::create([
            //     'uuid' => Str::uuid(),
            //     'name' => $request->validated('name'),
            // ]);
            $channel = $this->channel->store($request->validated('name'));

            // $channel->users()->sync([Auth::id()]);
            $this->channel->addFirstMember($channel, Auth::id());
            return $channel;
        });
        return new ChannelResource($channel);
    }


    public function join(Request $request, string $uuid)
    {
        $channel = Channel::where('uuid', $uuid)->first();
        if (!$channel) {
            abort(404, 'Not Found.');
        }
        if ($channel->users()->find(Auth::id())) {
            throw ValidationException::withMessages([
                'uuid' => 'Already Joined.',
            ]);
        }

        $channel->users()->attach(Auth::id());

        return response()->noContent();
    }

    
    public function leave(Request $request, string $uuid)
    {
        $channel = Channel::where('uuid', $uuid)->first();
        if (!$channel) {
            abort(404, 'Not Found.');
        }
        if (!$channel->users()->find(Auth::id())) {
            throw ValidationException::withMessages([
                'uuid' => 'Already Left.',
            ]);
        }

        $channel->users()->detach(Auth::id());

        return response()->noContent();
    }
}
