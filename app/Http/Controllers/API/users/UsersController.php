<?php

namespace App\Http\Controllers\API\users;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    //
    public function index()
    {
        $users = User::with('role', 'department')->orderBy('created_at','desc')->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);

    }

    public function engineeringUsers()
    {
        $users = User::whereHas('role', function ($q) {
                $q->whereIn('name', [
                    'engineer',
                    'engineer_supervisor',
                    'project manager',
                    'project controller',
                    'engineering_admin',
                    'engineering_director',
                    'electrician_supervisor',
                    'site_engineer',
                    'electrician',
                    'drafter'
                ]);
            })
            ->with('role')
            ->get();

        return response()->json([
            'success' => true,
            'category' => 'engineering',
            'data' => $users,
        ]);
    }

    public function marketingUsers()
    {
        $users = User::whereHas('role', function ($q) {
                $q->whereIn('name', [
                    'supervisor marketing',
                    'marketing_admin',
                    'marketing_director',
                    'marketing_estimator',
                    'sales_supervisor',
                ]);
            })
            ->with('role')
            ->get();

        return response()->json([
            'success' => true,
            'category' => 'marketing',
            'data' => $users,
        ]);
    }

    public function engineerOnly()
    {
        // pastikan role_id dan id sama tipe bigint
        $users = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['engineer',
                    'engineer_supervisor',
                    'project manager',
                    'project controller',
                    'engineering_admin',
                    'electrician_supervisor',
                    'site_engineer',
                    'electrician',
                    'drafter']);
        })
        ->with(['role' => function ($q) {
            $q->select(['id', 'name', 'type_role']); // pilih field spesifik
        }])
        ->select(['id', 'name', 'email', 'role_id']) // pilih field spesifik
        ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function roleTypeTwoOnly()
    {
        $roles = Role::where('type_role', 2)
            ->select(['id', 'name', 'type_role']) // pilih field spesifik
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }


    public function manPowerUsers()
    {
        $users = User::whereHas('role', function ($q) {
                $q->whereIn('name', [
                    'engineer',
                    'electrician',
                    'project manager',
                    'engineer_supervisor',
                    'engineering_admin',
                    'drafter',
                    'site_engineer',
                    'electrician_supervisor'
                ]);
            })
            ->with('role')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function manPowerRoles()
    {
        $roles = Role::where('type_role', 2)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    // POST /api/users
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'role_id'       => 'required|exists:roles,id',
            'department_id' => 'required|exists:departments,id',
            'pin'           => 'nullable|string|size:6',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['pin'] = Hash::make($validated['pin']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'data'    => $user->load(['role', 'department']),
        ], 201);
    }

    // GET /api/users/{id}
    public function show(User $user)
    {
        return response()->json(
            $user->load(['role', 'department'])
        );
    }

    // PUT /api/users/{id}
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => ['sometimes','required','email', Rule::unique('users')->ignore($user->id)],
            'password'      => 'nullable|string|min:6',
            'role_id'       => 'sometimes|required|exists:roles,id',
            'department_id' => 'sometimes|required|exists:departments,id',
            'pin'           => 'nullable|string|size:6',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => $user->load(['role', 'department']),
        ]);
    }

    // DELETE /api/users/{id}
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function uploadPhoto(Request $request, User $user)
    {
        $allowedRoles = [
            'project manager',
            'engineer_supervisor',
            'engineer',
            'electrician_supervisor',
            'electrician',
            'drafter',
        ];

        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && Storage::exists($user->photo)) {
                Storage::delete($user->photo);
            }

            $path = $request->file('photo')->store('user_photos', 'public');


            try {
                $user->photo = $path;
                $user->save();
                
                return response()->json([
                    'message' => 'Photo uploaded successfully',
                    'data' => $user
                ]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Failed to save photo: ' . $e->getMessage()], 500);
            }

        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }


}
