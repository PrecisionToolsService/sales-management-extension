define("push-notification:views/site/navbar", ["views/site/navbar"], function (
    Dep
) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);

            this.initPWA();
            this.initOneSignal();
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
                                "client/custom/modules/push-notification/services/pwa/PwaServiceWorker.js"
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
        initOneSignal: function () {
            const userName = this.getUser().get("userName");
            const onesignalAppId = this.getConfig().get("onesignalAppId");
            console.log(onesignalAppId);
            if (!onesignalAppId) {
                return;
            }
            window.OneSignalDeferred = window.OneSignalDeferred || [];
            window.OneSignalDeferred.push(async function (OneSignal) {
                try {
                    await OneSignal.init({
                        appId: onesignalAppId,
                        notifyButton: { enable: true },
                        serviceWorkerParam: {
                            scope: "/client/custom/modules/push-notification/services/onesignal/",
                        },
                        serviceWorkerPath:
                            "/client/custom/modules/push-notification/services/onesignal/OneSignalSDKWorker.js",
                    });
                    OneSignal.login(userName);
                    console.log("✅ OneSignal initialized successfully.");
                } catch (error) {
                    console.error("❌ OneSignal initialization failed:", error);
                }
            });
        },
    });
});
