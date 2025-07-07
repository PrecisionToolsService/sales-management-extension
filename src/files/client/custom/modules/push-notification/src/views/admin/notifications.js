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
        }
    }
    _exports.default = _default;
});
