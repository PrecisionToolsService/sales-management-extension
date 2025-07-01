define("desktop-notification:views/site/navbar", [
    "views/site/navbar",
], function (Dep) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            let onesignalAppId = this.getConfig().get("onesignalAppId");
            this.initPWA();
            this.initOneSignal(onesignalAppId);
        },

        // PWA initialization script
        initPWA: function () {
            navigator.serviceWorker
                .getRegistrations()
                .then(function (registrations) {
                    for (let registration of registrations) {
                        registration.unregister().then((success) => {
                            console.log("Unregistered:", success);
                        });
                    }
                    if ("serviceWorker" in navigator) {
                        navigator.serviceWorker
                            .register(
                                "client/custom/modules/desktop-notification/services/pwa/PwaServiceWorker.js"
                            )
                            .then(
                                function (registration) {
                                    console.log(
                                        "ServiceWorker registration successful with scope: ",
                                        registration.scope
                                    );
                                },
                                function (err) {
                                    console.log(
                                        "ServiceWorker registration failed: ",
                                        err
                                    );
                                }
                            );
                    }
                });
        },
        // OneSignal initialization
        initOneSignal: function (onesignalAppId) {
            window.OneSignalDeferred = window.OneSignalDeferred || [];
            window.OneSignalDeferred.push(async function (OneSignal) {
                try {
                    await OneSignal.init({
                        appId: onesignalAppId,
                        serviceWorkerParam: {
                            scope: "/client/custom/modules/desktop-notification/services/onesignal",
                        },
                        serviceWorkerPath:
                            "client/custom/modules/desktop-notification/services/onesignal/OneSignalSDKWorker.js",
                    });
                    console.log("✅ OneSignal initialized successfully.");
                } catch (error) {
                    console.error("❌ OneSignal initialization failed:", error);
                }
            });
        },
    });
});
