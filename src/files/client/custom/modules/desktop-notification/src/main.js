// PWA initialization script
window.addEventListener("load", () => {
    navigator.serviceWorker.getRegistrations().then(function (registrations) {
        for (let registration of registrations) {
            registration.unregister().then((success) => {
                console.log("Unregistered:", success);
            });
        }
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker
                .register(
                    "client/custom/modules/desktop-notification/others/admin-sw.js"
                )
                .then(
                    function (registration) {
                        console.log(
                            "ServiceWorker registration successful with scope: ",
                            registration.scope
                        );
                    },
                    function (err) {
                        console.log("ServiceWorker registration failed: ", err);
                    }
                );
        }
    });
});

// OneSignal initialization
window.OneSignalDeferred = window.OneSignalDeferred || [];
window.OneSignalDeferred.push(async function (OneSignal) {
    try {
        await OneSignal.init({
            appId: "{{ONESIGNAL_APP_ID}}",
            serviceWorkerParam: {
                scope: "/client/custom/modules/desktop-notification/others/",
            },
            serviceWorkerPath:
                "client/custom/modules/desktop-notification/others/OneSignalSDKWorker.js",
        });
        console.log("✅ OneSignal initialized successfully.");
    } catch (error) {
        console.error("❌ OneSignal initialization failed:", error);
    }
});
