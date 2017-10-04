// Import and configure the Firebase SDK
// These scripts are made available when the app is served or deployed on Firebase Hosting
// If you do not serve/host your project using Firebase Hosting see https://firebase.google.com/docs/web/setup
//importScripts('https://www.gstatic.com/firebasejs/4.3.1/firebase-app.js');
//importScripts('https://www.gstatic.com/firebasejs/4.3.1/firebase-messaging.js');

// const messaging = firebase.messaging();

/**
 * Here is is the code snippet to initialize Firebase Messaging in the Service
 * Worker when your app is not hosted on Firebase Hosting.
 * */

 // [START initialize_firebase_in_sw]
 // Give the service worker access to Firebase Messaging.
 // Note that you can only use Firebase Messaging here, other Firebase libraries
 // are not available in the service worker.
 importScripts('https://www.gstatic.com/firebasejs/3.9.0/firebase-app.js');
 importScripts('https://www.gstatic.com/firebasejs/3.9.0/firebase-messaging.js');

 // Initialize the Firebase app in the service worker by passing in the
 // messagingSenderId.
var config = {
    apiKey: "AIzaSyCQlLNOJ30MflNJA5IT79AxG7clW2L5bUg ",
    messagingSenderId: "244361723958"
};
firebase.initializeApp(config);

 // Retrieve an instance of Firebase Messaging so that it can handle background
 // messages.
 const messaging = firebase.messaging();
 // [END initialize_firebase_in_sw]


// If you would like to customize notifications that are received in the
// background (Web app is closed or not in browser focus) then you should
// implement this optional method.
// [START background_handler]
messaging.setBackgroundMessageHandler(function(payload) {
  console.log('[sw.js] Received background message ', payload);
    var data = payload.data;
    var title = data.title;
    var options = {
        'body': data.body,
        'icon': data.icon,
        'data': data
    };
    setTimeout(function() {
        self.registration.showNotification(title, options).then(function(event) {
            console.log('Notification event: ', event);
        });
    }, parseInt(data.timeout)*1000);
});
self.addEventListener('notificationclick', function(event) {
    console.log('On notification click: ', event.notification);
    event.notification.close();
    event.waitUntil(clients.matchAll({
        type: "window"
    }).then(function(clientList) {
        for (var i = 0; i < clientList.length; i++) {
            var client = clientList[i];
            if (client.url == '/' && 'focus' in client)
                return client.focus();
        }
        if (clients.openWindow) {
            return clients.openWindow(event.notification.data.url);
        }
    }));
});
// [END background_handler]
