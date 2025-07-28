define("modules/assignment/handlers/user/change-account-role-row-action", [
    "exports",
    "handlers/row-action",
    "modules/assignment/views/account/modals/select-role",
], function (e, t, s) {
    "use strict";
    function a(e) {
        return e && e.__esModule ? e : { default: e };
    }
    Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0),
        (t = a(t)),
        (s = a(s));
    class i extends t.default {
        async process(e, t) {
            if (!e.collection || !e.collection.parentModel)
                return void console.error("Account model cannot be obtained.");
            const a = e.collection.parentModel,
                i = new s.default({
                    members: [e],
                    role:
                        e.attributes.accountRoleId || e.attributes.accountRole,
                    onSelect: async (t) => {
                        Espo.Ui.notify(" ... "),
                            await Espo.Ajax.postRequest(
                                `Account/${a.id}/members`,
                                {
                                    ids: [e.id],
                                    role: t,
                                }
                            ),
                            Espo.Ui.success(this.view.translate("Done")),
                            await this.collection.fetch();
                    },
                });
            await this.view.assignView("dialog", i), await i.render();
        }
        isAvailable(e, t) {
            return (
                !(!e.collection || !e.collection.parentModel) &&
                this.view.getAcl().checkModel(e.collection.parentModel, "edit")
            );
        }
    }
    e.default = i;
});
