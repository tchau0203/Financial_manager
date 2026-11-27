const CACHE_NAME = 'cashmanager-static-v7'; // tăng phiên bản để xóa cache cũ và nhận file mới
const APP_BASE = new URL('./', self.location.href);

const STATIC_FILES = [
  'index.html',
  'manifest.json',
  'pages/login.html',
  'pages/register.html',
  'pages/dashboard.html',
  'pages/income.html',
  'pages/expense.html',
  'pages/budget.html',
  'pages/report.html',
  'pages/account.html',
  'pages/shared.html',
  'pages/map.html',
  'assets/css/style.css',
  'assets/css/components.css',
  'assets/js/auth.js',
  'assets/js/header_nav.js',
  'assets/js/account.js',
  'assets/js/ai.js',
  'assets/js/budget.js',
  'assets/js/dashboard.js',
  'assets/js/expense.js',
  'assets/js/location_picker.js',
  'assets/js/map.js',
  'assets/js/income.js',
  'assets/js/pwa.js',
  'assets/js/report.js',
  'assets/js/search.js',
  'assets/js/settlement.js',
  'assets/js/shared.js',
  'assets/icons/icon-192.svg',
  'assets/icons/icon-512.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => Promise.allSettled(
         STATIC_FILES.map(file => cache.add(new URL(file, APP_BASE)))
        ))


      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(key => key.startsWith('cashmanager-') && key !== CACHE_NAME)
          .map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  const appPath = APP_BASE.pathname;

 
  if (
    request.method !== 'GET' ||
    url.origin !== self.location.origin ||
    url.pathname.startsWith(`${appPath}api/`)
  ) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then(response => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
          }
          return response;
        })
        .catch(async () => {
          return (await caches.match(request)) ||
            (await caches.match(new URL('pages/login.html', APP_BASE)));
        })
    );
    return;
  }

  if (
    url.pathname.startsWith(`${appPath}assets/`) ||
    url.pathname.endsWith('/manifest.json')
  ) {
    // Ưu tiên tải bản mới nhất từ máy chủ (luôn thấy code mới sau khi cập nhật);
    // chỉ dùng bản đã lưu khi mất mạng.
    event.respondWith(
      fetch(request)
        .then(response => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
          }
          return response;
        })
        .catch(async () => (await caches.match(request)) || Response.error())
    );
  }
});


self.addEventListener('push', event => {
  let data = {};

  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { body: event.data ? event.data.text() : '' };
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'CashManager', {
      body: data.body || 'Hôm nay bạn có khoản chi nào chưa ghi lại không?',
      icon: new URL('assets/icons/icon-192.svg', APP_BASE).href,
      badge: new URL('assets/icons/icon-192.svg', APP_BASE).href,
      data: { url: data.url || 'pages/expense.html' }
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();

  const target = new URL(event.notification.data?.url || 'pages/expense.html', APP_BASE);

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clients => {
        const existing = clients.find(client => client.url === target.href);
        return existing ? existing.focus() : self.clients.openWindow(target.href);
      })
  );
});