<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :hide-heading="true">
    @php
        $totalAssignments = $roles->sum('users_count');
        $systemRoles = $roles->where('slug', \App\Models\Role::SUPER_ADMINISTRATOR)->count();
        $tones = ['blue', 'green', 'amber', 'purple', 'teal', 'pink'];
    @endphp

    <nav class="module-tabs" aria-label="identity sections">
        <a href="#">overview</a>
        <a href="#">users</a>
        <a class="is-active" href="#">roles</a>
        <a href="#">permissions</a>
        <a href="#">audit</a>
    </nav>

    <section class="role-hero">
        <div>
            <h2>role management</h2>
            <p>create, edit and manage system access roles</p>
        </div>
        <a class="button button-primary" href="#">+ new role</a>
    </section>

    <section class="role-summary-strip" aria-label="role summary">
        <div>
            <strong>{{ $roles->count() }}</strong>
            <span>active roles</span>
        </div>
        <div>
            <strong>{{ $totalAssignments }}</strong>
            <span>user assignments</span>
        </div>
        <div>
            <strong>{{ $systemRoles }}</strong>
            <span>system role</span>
        </div>
    </section>

    <section class="role-search-strip">
        <label>
            <span>search</span>
            <input type="search" placeholder="search by name or code...">
        </label>
        <p>{{ $roles->count() }} roles shown</p>
        <p>click a card to manage its permissions -></p>
    </section>

    <section class="role-card-grid" aria-label="roles">
        @foreach ($roles as $role)
            @php($tone = $tones[$loop->index % count($tones)])
            <article class="role-card role-card-{{ $tone }}">
                <div class="role-card-body">
                    <h3>{{ strtolower($role->name) }}</h3>
                    <span class="slug-pill">{{ $role->slug }}</span>
                    <p>{{ strtolower($role->description ?: 'system access profile') }}</p>

                    <div class="role-metrics">
                        <div>
                            <strong>{{ $role->users_count }}</strong>
                            <span>users</span>
                        </div>
                        <div>
                            <strong>{{ $role->permissions->count() }}</strong>
                            <span>permissions</span>
                        </div>
                    </div>
                </div>

                <div class="role-card-footer">
                    <a class="mini-button" href="#">edit</a>
                    <a class="mini-link" href="#">permissions</a>
                    <a class="mini-link" href="#">members</a>
                    <a class="mini-link" href="#">clone</a>
                    <button class="mini-button danger-button" type="button">delete</button>
                </div>
            </article>
        @endforeach
    </section>

    <x-ui.card title="permissions" subtitle="server-side abilities available to configured roles">
        <div class="permission-list">
            @foreach ($permissions as $permission)
                <div>
                    <strong>{{ $permission->slug }}</strong>
                    <span>{{ strtolower($permission->name) }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</x-layouts.app>
