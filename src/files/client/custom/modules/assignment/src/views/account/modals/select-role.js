define("modules/assignment/views/account/modals/select-role", [
    "exports",
    "views/modal",
    "model",
    "views/record/edit-for-modal",
    "views/fields/enum",
    "views/fields/multi-enum",
], function (e, t, s, a, i, n) {
    "use strict";
    function o(e) {
        return e && e.__esModule ? e : { default: e };
    }
    Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0),
        (t = o(t)),
        (s = o(s)),
        (a = o(a)),
        (i = o(i)),
        (n = o(n));
    class r extends t.default {
        templateContent =
            '\n        <div class="record no-side-margin">{{{record}}}</div>\n    ';
        constructor(e) {
            super(e),
                (this.members = e.members),
                (this.onSelect = e.onSelect),
                (this.currentRole = e.role);
        }
        setup() {
            (this.headerText = this.translate(
                "Select Role",
                "labels",
                "Account"
            )),
                this.buttonList.push({
                    name: "apply",
                    label: "Apply",
                    style: "danger",
                    onClick: () => this.actionApply(),
                }),
                this.buttonList.push({
                    name: "cancel",
                    label: "Cancel",
                    onClick: () => this.actionClose(),
                }),
                (this.model = new s.default({
                    role: this.currentRole || null,
                    members: this.members.map((e) => e.attributes.name),
                }));
            const e = this.getHelper().getAppParam("accountRoles") || [],
                t = ["", ...e.map((e) => e.id), "Editor", "Owner"],
                o = t.reduce(
                    (e, t) => (
                        (e[t] = this.getLanguage().translateOption(
                            t,
                            "accountRole",
                            "User"
                        )),
                        e
                    ),
                    {}
                );
            e.forEach((e) => (o[e.id] = e.name)),
                (this.recordView = new a.default({
                    model: this.model,
                    detailLayout: [
                        {
                            rows: [
                                [
                                    {
                                        view: new i.default({
                                            name: "role",
                                            params: {
                                                options: t,
                                                translatedOptions: o,
                                            },
                                            labelText: this.translate(
                                                "role",
                                                "otherFields",
                                                "Account"
                                            ),
                                        }),
                                    },
                                    {
                                        view: new n.default({
                                            name: "members",
                                            labelText: this.translate(
                                                "members",
                                                "links",
                                                "Account"
                                            ),
                                            params: {
                                                readOnly: !0,
                                                displayAsList: !0,
                                            },
                                        }),
                                    },
                                ],
                            ],
                        },
                    ],
                })),
                this.assignView("record", this.recordView, ".record");
        }
        actionApply() {
            this.recordView.validate() ||
                (this.onSelect(this.model.attributes.role), this.close());
        }
    }
    e.default = r;
});
