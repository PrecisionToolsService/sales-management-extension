define("modules/assignment/views/account-role/fields/permission", [
    "exports",
    "views/fields/enum",
], function (e, t) {
    "use strict";
    var s;
    Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0),
        (t = (s = t) && s.__esModule ? s : { default: s });
    class a extends t.default {
        setup() {
            (this.params.style = {
                yes: "success",
                all: "success",
                assigned: "info",
                own: "warning",
                no: "danger",
            }),
                super.setup();
        }
    }
    e.default = a;
});
