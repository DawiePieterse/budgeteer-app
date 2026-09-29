// Turns phone notifications on or off for this phone or browser (Settings screen).
(function () {
    const box = document.getElementById('push');
    if (!box) {
        return;
    }
    const status = box.querySelector('[data-push-status]');
    const onButton = box.querySelector('[data-push-on]');
    const offButton = box.querySelector('[data-push-off]');
    const iosHint = box.querySelector('[data-push-ios]');
    const key = box.dataset.key;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const say = (text) => { status.textContent = text; };
    const show = (on) => { onButton.hidden = on; offButton.hidden = !on; };

    const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        if (isIos && !standalone) {
            iosHint.hidden = false;
            say('On iPhone, notifications work once Budgeteer is on your Home Screen.');
        } else {
            say('This browser cannot show notifications.');
        }
        return;
    }
    if (!key) {
        say('Phone notifications are not set up on the server yet.');
        return;
    }

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    }).then((response) => {
        if (!response.ok) {
            throw new Error('The server said ' + response.status);
        }
    });

    const keyBytes = () => {
        const padded = (key + '='.repeat((4 - key.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    };

    const device = () => {
        const ua = navigator.userAgent;
        const os = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android' : /Mac/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows' : 'Browser';
        const browser = /Edg\//.test(ua) ? 'Edge' : /SamsungBrowser/.test(ua) ? 'Samsung Internet' : /Firefox/.test(ua) ? 'Firefox' : /Chrome/.test(ua) ? 'Chrome' : /Safari/.test(ua) ? 'Safari' : '';
        return (os + ' ' + browser).trim();
    };

    let registration;
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then((reg) => { registration = reg; return reg.pushManager.getSubscription(); })
        .then((subscription) => {
            if (subscription && Notification.permission === 'granted') {
                // Tell the server again, in case it forgot this phone.
                post(box.dataset.store, Object.assign(subscription.toJSON(), { device: device() })).catch(() => {});
                say('Notifications are on for this phone.');
                show(true);
            } else if (Notification.permission === 'denied') {
                say('Notifications are blocked for Budgeteer. Allow them in this phone\'s settings, then reload.');
                onButton.hidden = true;
            } else {
                say('Notifications are off for this phone.');
                show(false);
            }
        })
        .catch((e) => say('Could not start notifications: ' + e.message));

    onButton.addEventListener('click', async () => {
        try {
            onButton.disabled = true;
            if (await Notification.requestPermission() !== 'granted') {
                say('Notifications were not allowed.');
                return;
            }
            const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes() });
            await post(box.dataset.store, Object.assign(subscription.toJSON(), { device: device() }));
            say('Notifications are on for this phone. Reload to see it in the list below, or send a test.');
            show(true);
        } catch (e) {
            say('Could not turn notifications on: ' + e.message);
        } finally {
            onButton.disabled = false;
        }
    });

    offButton.addEventListener('click', async () => {
        try {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await post(box.dataset.destroy, { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }
            say('Notifications are off for this phone.');
            show(false);
        } catch (e) {
            say('Could not turn notifications off: ' + e.message);
        }
    });
})();
