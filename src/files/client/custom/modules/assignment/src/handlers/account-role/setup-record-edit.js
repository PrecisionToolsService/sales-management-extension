define("modules/assignment/handlers/account-role/setup-record-edit", [
    "exports",
], function (e) {
    "use strict";
    Object.defineProperty(e, "__esModule", { value: !0 }), (e.default = void 0);
    e.default = class {
        constructor(e) {
            (this.view = e), (this.model = e.model);
        }
        process() {
            this.model.id ||
                this.view.on("after:save", () => {
                    this.view.getHelper().appParams &&
                        this.view.getHelper().appParams.load &&
                        (this.view.getHelper().appParams.load(),
                        this.view
                            .getHelper()
                            .broadcastChannel.postMessage("update:appParams"));
                });
        }
    };
});
