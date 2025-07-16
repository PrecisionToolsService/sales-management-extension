define("push-notification:views/admin/notifications", [
    "exports",
    "views/settings/record/edit",
], function (_exports, _edit) {
    "use strict";

    Object.defineProperty(_exports, "__esModule", {
        value: true,
    });
    _exports.default = void 0;
    _edit = _interopRequireDefault(_edit);
    function _interopRequireDefault(e) {
        return e && e.__esModule ? e : { default: e };
    }

    class _default extends _edit.default {
        layoutName = "push-notifications";
        saveAndContinueEditingAction = false;
        dynamicLogicDefs = {
            fields: {},
        };
        setup() {
            super.setup();
            this.controlStreamPushNotificationsEntityList();
            this.listenTo(this.model, "change", (model) => {
                if (
                    model.hasChanged("streamPushNotifications") ||
                    model.hasChanged("teamsStreamPushNotifications") ||
                    model.hasChanged("followerStreamPushNotifications") ||
                    model.hasChanged("mentionPushNotifications")
                ) {
                    this.controlStreamPushNotificationsEntityList();
                }
            });
        }

        controlStreamPushNotificationsEntityList() {
            if (
                this.model.get("streamPushNotifications") ||
                this.model.get("teamsStreamPushNotifications") ||
                this.model.get("followerStreamPushNotifications") ||
                this.model.get("mentionPushNotifications")
            ) {
                this.showField("streamPushNotificationsEntityList");
                this.showField("streamPushNotificationsTypeList");
            } else {
                this.hideField("streamPushNotificationsEntityList");
                this.hideField("streamPushNotificationsTypeList");
            }
        }
    }
    _exports.default = _default;
});
