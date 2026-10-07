importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js');

const firebaseConfig = {
    apiKey: "AIzaSyAwYUCDNT-2HGqN0eYvS2N_C-8wn7fWbwE",
    authDomain: "new-ptm-app.firebaseapp.com",
    projectId: "new-ptm-app",
    storageBucket: "new-ptm-app.appspot.com",
    messagingSenderId: "876353677089",
    appId: "1:876353677089:web:a013a654db20239e034060",
    measurementId: "G-KV7EDT5SGC"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
const messaging =  firebase.messaging();
console.log(messaging);
messaging.onBackgroundMessage((payload) => {
    console.log('onBackgroundMessage method called');
    // console.log(
    //     '[firebase-messaging-sw.js] Received background message ',
    //     payload
    // );
    // const {title,body} = payload.notification;

    // const notificationOptions = {
    //     body,
    // };

    // self.registration.showNotification(title, notificationOptions);
});