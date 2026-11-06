<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :actions="$actions">
    <x-ui.card title="user accounts" subtitle="manage access to the mdsiss platform">
        <x-ui.table :headers="['name', 'email', 'telephone', 'designation', 'roles', 'status', 'last login', '']">
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->telephone ?? '-' }}</td>
                    <td>{{ $user->designation ?? '-' }}</td>
                    <td>
                        <div class="badge-stack">
                            @forelse ($user->roles as $role)
                                <x-ui.badge>{{ strtolower($role->name) }}</x-ui.badge>
                            @empty
                                <x-ui.badge tone="warning">no role</x-ui.badge>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <x-ui.badge :tone="$user->isActive() ? 'success' : 'warning'">
                            {{ $user->status }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>
                        @can('users.manage')
                            <a class="table-action" href="{{ route('administration.users.edit', $user) }}">edit</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-ui.empty-state
                            title="no users"
                            message="create the first user account to begin assigning access."
                            action-label="create user"
                            :action-url="route('administration.users.create')"
                        />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div class="native-pagination">
            {{ $users->links() }}
        </div>
    </x-ui.card>
</x-layouts.app>
