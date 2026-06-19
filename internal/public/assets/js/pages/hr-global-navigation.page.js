function initNavAuthPage() {
    const homeItem = byId('nav-home-item');
    const usersItem = byId('nav-hr-users-item');
    const reportsItem = byId('nav-hr-reports-item');
    const categoriesItem = byId('nav-hr-categories-item');
    const statusesItem = byId('nav-hr-statuses-item');
    const stagesItem = byId('nav-hr-stages-item');
    const rolesItem = byId('nav-hr-roles-item');
    const hrConsoleItem = byId('nav-hr-console-item');
    const loginItem = byId('nav-hr-login-item');
    const logoutItem = byId('nav-hr-logout-item');
    const logoutBtn = byId('nav-hr-logout');
    const protectedNavItems = [
        usersItem,
        reportsItem,
        categoriesItem,
        statusesItem,
        stagesItem,
        rolesItem,
        hrConsoleItem,
    ];

    const update = (isLoggedIn) => {
        const isReportsPage = globalThis.location.pathname === '/anonymized/reports';

        if (homeItem) {
            homeItem.style.display = isReportsPage && !isLoggedIn ? 'none' : '';
        }

        protectedNavItems.forEach((item) => {
            if (item) {
                item.style.display = isLoggedIn ? '' : 'none';
            }
        });
        if (loginItem) loginItem.style.display = isLoggedIn ? 'none' : '';
        if (logoutItem) logoutItem.style.display = isLoggedIn ? '' : 'none';
    };

    update(TokenManager.hasToken());

    logoutBtn?.addEventListener('click', async () => {
        try {
            await api(`${API_BASE}/hr/logout`, { method: 'POST' });
        } catch (err) {
            console.warn('HR logout request failed.', err);
        }
        TokenManager.clearToken();
        update(false);
        showNotification('Logged out successfully!', 'success');
        globalThis.location.href = '/hr/login';
    });

    globalThis._navAuthUpdate = update;
}

document.addEventListener('DOMContentLoaded', () => {
    initNavAuthPage();
});
