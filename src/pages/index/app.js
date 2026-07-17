import { createApp } from "vue";
import App from "./App.vue";

import PrimeVue from "primevue/config";
import Lara from '@primevue/themes/lara';

import ProgressSpinner from 'primevue/progressspinner';
import Dialog from "primevue/dialog";
import Tooltip from 'primevue/tooltip';

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Lara
    }
});

app.directive('tooltip', Tooltip);

app.component("Dialog", Dialog);
app.component("ProgressSpinner", ProgressSpinner);

app.mount("#GOOGLE_PHOTOS_INDEX");