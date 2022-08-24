import {createStore} from 'vuex'
import Profile from './Profile'
import Utils from './Utils'
import SharePostModal from './SharePostModal'
import HttpUtils from "./HttpUtils";
import Chat from "./Chat";

export default createStore({
    modules: {
        Profile,
        Utils,
        SharePostModal,
        HttpUtils,
        Chat,
    }
})
