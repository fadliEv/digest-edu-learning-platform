<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of all users with search, role filters, and statistics.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->input('search', ''));
        $role   = trim($request->input('role', ''));
        $status = trim($request->input('status', ''));

        $query = User::query()->orderBy('created_at', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($status !== '') {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->paginate(10)->withQueryString()->through(function (User $user) {
            return [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'role'              => $user->role,
                'is_active'          => (bool) $user->is_active,
                'email_verified_at' => $user->email_verified_at?->format('d M Y H:i'),
                'created_at'        => $user->created_at?->format('d M Y'),
            ];
        });

        $stats = [
            'total_users'    => User::count(),
            'total_admins'   => User::where('role', 'admin')->count(),
            'total_students' => User::where('role', 'user')->count(),
            'active_users'   => User::where('is_active', true)->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users'       => $users,
            'filters'     => [
                'search' => $search,
                'role'   => $role,
                'status' => $status,
            ],
            'stats'       => $stats,
            'currentUser' => [
                'id' => $request->user()->id,
            ],
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email',
            'password'  => 'required|string|min:8',
            'role'      => 'required|in:admin,user',
            'is_active' => 'nullable|boolean',
        ]);

        User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password'  => 'nullable|string|min:8',
            'role'      => 'required|in:admin,user',
            'is_active' => 'nullable|boolean',
        ]);

        $userData = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        return redirect()->route('admin.users.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    /**
     * Toggle active status of a user.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.users.index')->with('success', "Akun pengguna {$user->name} berhasil {$statusText}.");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}
