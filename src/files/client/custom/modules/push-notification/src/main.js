define("push-notification:views/site/navbar", [
    "views/site/navbar",
    "model",
], function (Dep, Model) {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.model = new Model();
            this.model.urlRoot = "Integration";
            this.model.id = "PushNotification";
            this.initPWA();
            this.model.fetch().then(() => this.initOneSignal());
        },

        // PWA initialization script
        initPWA: function () {
            navigator.serviceWorker
                .getRegistrations()
                .then(function (registrations) {
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
            const safari_web_id = this.getConfig().get("onesignalSafariId");
            if (!onesignalAppId) {
                return;
            }
            window.OneSignalDeferred = window.OneSignalDeferred || [];
            window.OneSignalDeferred.push(async function (OneSignal) {
                OneSignal.Debug.setLogLevel("trace");
                try {
                    await OneSignal.init({
                        appId: onesignalAppId,
                        safari_web_id: safari_web_id,
                        autoResubscribe: true,
                        persistNotification: false,
                        allowLocalhostAsSecureOrigin: true,
                        promptOptions: {
                            slidedown: {
                                prompts: [
                                    {
                                        autoPrompt: true,
                                        delay: { pageViews: 2 },
                                        type: "push",
                                    },
                                ],
                            },
                        },
                        serviceWorkerParam: {
                            scope: "/client/custom/modules/push-notification/services/onesignal/",
                        },
                        serviceWorkerPath:
                            "client/custom/modules/push-notification/services/onesignal/OneSignalSDKWorker.js",
                    });
                    await OneSignal.login(userName);
                    console.log("✅ OneSignal initialized successfully.");
                } catch (error) {
                    console.error("❌ OneSignal initialization failed:", error);
                }
            });
        },
    });
});
