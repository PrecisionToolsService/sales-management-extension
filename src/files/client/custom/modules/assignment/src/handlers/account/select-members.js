define("modules/assignment/handlers/account/select-members", [
    "exports",
], function (e) {
    "use strict";
    Object.defineProperty(e, "__esModule", { value: !0 }), (e.default = void 0);
    e.default = class {
        constructor(e) {
            this.viewHelper = e;
        }
        async getFilters(e) {
            const t = this.viewHelper.acl.getPermissionLevel("assignment");
            return "team" === t
                ? Promise.resolve({ bool: ["onlyMyTeam"] })
                : "no" === t
                ? Promise.resolve({ bool: ["onlyMe"] })
                : {};
        }
    };
});
