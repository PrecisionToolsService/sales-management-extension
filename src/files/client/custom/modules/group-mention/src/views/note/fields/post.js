define("group-mention:views/note/fields/post", [
    "views/note/fields/post",
], function (Dep) {
    return Dep.extend({
        setup: function () {
            console.log("setup");
            Dep.prototype.setup.call(this);
        },
        afterRenderEdit: function () {
            console.log("afterRenderEdit");
            Dep.prototype.afterRenderEdit.call(this);
        },
        getMentionType(mention) {
            if (mention.userName) return "User";
            if (mention.userPermission) return "Role";
            else return "Team";
        },
        mentionTemplateHelper(mention) {
            var labelText = '<span class="text-muted" style="display:inline-block; width:40px;"> ' +
                this.getMentionType(mention) +
                "</span>";
            if (this.getMentionType(mention) == "User")
                labelText += this.getHelper().getAvatarHtml(
                    mention.id,
                    "medium",
                    16,
                    "avatar-link"
                )

            labelText += this.getHelper().escapeString(mention.name)
            return labelText;
        },
        initMentions() {
            console.log("initMentions");
            const mentionPermissionLevel =
                this.getAcl().getPermissionLevel("mention");
            if (mentionPermissionLevel === "no" /*|| this.model.isNew()*/) {
                return;
            }
            const maxSize = this.getConfig().get("recordsPerPage");
            const buildUserListUrl = (term) => {
                let url =
                    `User?` +
                    `${$.param({
                        q: term,
                    })}` +
                    `&${$.param({
                        primaryFilter: "active",
                    })}` +
                    `&orderBy=name` +
                    `&maxSize=${maxSize}` +
                    `&select=id,name,userName`;
                if (mentionPermissionLevel === "team") {
                    url +=
                        "&" +
                        $.param({
                            boolFilterList: ["onlyMyTeam"],
                        });
                }
                return url;
            };
            const buildTeamListUrl = (term) => {
                let url =
                    `Team?` +
                    `${$.param({
                        q: term,
                    })}` +
                    `&orderBy=name` +
                    `&maxSize=${maxSize}` +
                    `&select=id,name,userName`;
                if (mentionPermissionLevel === "team") {
                    url +=
                        "&" +
                        $.param({
                            boolFilterList: ["onlyMy"],
                        });
                }
                return url;
            };
            const buildRoleListUrl = (term) => {
                let url =
                    `Role?` +
                    `${$.param({
                        q: term,
                    })}` +
                    `&orderBy=name` +
                    `&maxSize=${maxSize}` +
                    `&select=id,name,userName`;
                return url;
            };

            // noinspection JSUnresolvedReference
            this.$element.textcomplete(
                [
                    {
                        match: /(^|\s)@(\w[\w@.-]*)$/,
                        index: 2,
                        // @todo Revise.
                        search: (term, callback) => {
                            if (term.length === 0) {
                                callback([]);
                                return;
                            }
                            Promise.all([
                                Espo.Ajax.getRequest(buildUserListUrl(term)),
                                Espo.Ajax.getRequest(buildTeamListUrl(term)),
                                Espo.Ajax.getRequest(buildRoleListUrl(term))
                            ]).then(([userData, teamData, roleData]) => {
                                const combinedList = [...userData.list, ...teamData.list, ...roleData.list];
                                callback(combinedList);
                            });
                        },
                        template: (mention) => this.mentionTemplateHelper(mention),
                        replace: (o) => {
                            if (this.getMentionType(o) == "User") return `$1@${o.userName} `;
                            else return `[${o.name}](#${this.getMentionType(o)}/view/${o.id}) `;
                        },
                    },
                ],
                {
                    zIndex: 1100,
                }
            );
            this.on("remove", () => {
                if (this.$element.length) {
                    this.$element.textcomplete("destroy");
                }
            });
        },
    });
});
