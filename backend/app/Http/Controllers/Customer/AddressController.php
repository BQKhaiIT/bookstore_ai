<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\AddressResource;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => AddressResource::collection($addresses),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedAddress($request);

        $address = DB::transaction(function () use ($request, $validated) {
            if (! empty($validated['is_default'])) {
                $request->user()->addresses()->update(['is_default' => false]);
            }

            return $request->user()->addresses()->create($validated);
        });

        return response()->json([
            'data' => new AddressResource($address),
        ], 201);
    }

    public function update(Request $request, UserAddress $address): JsonResponse
    {
        $this->assertOwnership($request, $address);
        $validated = $this->validatedAddress($request, partial: true);

        $address = DB::transaction(function () use ($request, $address, $validated) {
            if (! empty($validated['is_default'])) {
                $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->fill($validated);
            $address->save();

            return $address;
        });

        return response()->json([
            'data' => new AddressResource($address),
        ]);
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        $this->assertOwnership($request, $address);
        $address->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAddress(Request $request, bool $partial = false): array
    {
        $rules = [
            'recipient_name' => [$partial ? 'sometimes' : 'required', 'string', 'max:150'],
            'phone' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
            'province' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'district' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'ward' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'address_line' => [$partial ? 'sometimes' : 'required', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function assertOwnership(Request $request, UserAddress $address): void
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
