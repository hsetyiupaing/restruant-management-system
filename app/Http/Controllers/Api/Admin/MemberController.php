<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMemberRequest;
use App\Http\Requests\Api\UpdateMemberRequest;
use App\Http\Resources\RestaurantMemberResource;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MemberController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('manageMembers', $restaurant);

        $members = $restaurant->members()
            ->with('user')
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->orderBy('created_at')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return RestaurantMemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request, Restaurant $restaurant): RestaurantMemberResource
    {
        Gate::authorize('manageMembers', $restaurant);

        $member = $restaurant->members()->create($request->validated());

        return new RestaurantMemberResource($member->load('user'));
    }

    public function update(UpdateMemberRequest $request, Restaurant $restaurant, RestaurantMember $member): RestaurantMemberResource
    {
        Gate::authorize('manageMembers', $restaurant);

        abort_unless($member->restaurant_id === $restaurant->id, 404);

        $member->update($request->validated());

        return new RestaurantMemberResource($member->load('user'));
    }

    public function destroy(Restaurant $restaurant, RestaurantMember $member): Response
    {
        Gate::authorize('manageMembers', $restaurant);

        abort_unless($member->restaurant_id === $restaurant->id, 404);

        $member->delete();

        return response()->noContent();
    }
}
