define("modules/assignment/handlers/account/select-members-action", [
    "exports",
    "views/modals/select-records",
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
    e.default = class {
        constructor(e) {
            (this.view = e),
                (this.model = e.model),
                (this.collection = e.collection);
        }
        async select() {
            const e = this.view.getAcl().getPermissionLevel("assignment"),
                a = [];
            "team" === e && a.push("onlyMyTeam"),
                "no" === e && a.push("onlyMe");
            const i = new t.default({
                entityType: "User",
                multiple: !0,
                createButton: !1,
                boolFilterList: a,
                filters: {
                    accountAssignments: {
                        type: "notLinkedWith",
                        attribute: "accountAssignments",
                        value: [this.model.id],
                        data: {
                            type: "noneOf",
                            nameHash: {
                                [this.model.id]: this.model.attributes.name,
                            },
                        },
                    },
                },
            });
            this.view.listenToOnce(i, "select", async (e) => {
                const t = new s.default({
                    assignedUsers: e,
                    onSelect: async (attr) => {
                        Espo.Ui.notify(" ... "),
                            await Espo.Ajax.postRequest(
                                `Account/${this.model.id}/assignedUsers`,
                                {
                                    ids: e.map((e) => e.attributes.id),
                                    role: attr.role,
                                    synced: attr.synced,
                                }
                            ),
                            await this.collection.fetch(),
                            Espo.Ui.success(this.view.translate("Done")),
                            this.model.trigger("after:relate"),
                            this.model.trigger("after:relate:assignedUsers");
                    },
                });
                await this.view.assignView("dialog", t), await t.render();
            }),
                await this.view.assignView("dialog", i),
                await i.render();
        }
    };
});
