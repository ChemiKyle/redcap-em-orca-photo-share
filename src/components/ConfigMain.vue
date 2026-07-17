<script setup>
import { onBeforeMount, onMounted, useTemplateRef, ref, computed, nextTick } from "vue";
import { useToast } from 'primevue/usetoast';
import { isEmpty, isNotEmpty } from '@primeuix/utils/object';
import ModuleUtils from '../ModuleUtils';
import { DateTime, Interval } from "luxon";
import { useConfirm } from "primevue/useconfirm";

const debug = ref();
const config = ref({});
const isLoading = ref(false);
const isModified = ref(false);

const clientIdModalInput = useTemplateRef('client-id-modal');
const albumNameModalInput = useTemplateRef('album-name-modal');

const toast = useToast();

const confirm = useConfirm();
const confirmRefreshToken = () => {
    confirm.require({
        message: "You're about to re-authorize the app and generate a new refresh token.  Are you sure you wish to proceed?",
        header: 'Request new Refresh Token',
        icon: 'fa-solid fa-triangle-exclamation',
        rejectLabel: 'Cancel',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Proceed',
            severity: 'danger'
        },
        accept: () => {
            reAuthorizeClient();
        }
    });
};

/* CLIENT INFO LOGIC */
const clientInfoDialogState = ref({
    "client_id": null,
    "client_secret": null
});
const refreshTokenExpiresHours = computed(() => {
    if (isNotEmpty(config.value) && isNotEmpty(config.value['refresh_token_expires'])) {
        // DateTime.fromSQL is able to parse both date and datetime fields
        // https://moment.github.io/luxon/#/parsing?id=sql
        let d = DateTime.fromSQL(config.value['refresh_token_expires']);
        // calculate the diff with min/max, if set
        return d.diffNow('hours').hours;
    }
    return null;
});
const refreshTokenExpiresDisplay = computed(() => {
    if (refreshTokenExpiresHours.value > 0) {
        return `${config.value['refresh_token_expires']} (${ModuleUtils.hoursToHuman(refreshTokenExpiresHours.value)})`;
    }
    return `${config.value['refresh_token_expires']}`;
});
const clientInfoDialogVisible = ref(false);
const focusClientId = () => {
    setTimeout(() => {
        nextTick(() => clientIdModalInput.value.focus());
    }, 500);
}
const editClientInfo = () => {
    if (config) {
        clientInfoDialogVisible.value = true;
    }
};
const canSaveClientInfo = computed(() => {
    return isNotEmpty(clientInfoDialogState.value)
        && isNotEmpty(clientInfoDialogState.value['client_id'])
        && isNotEmpty(clientInfoDialogState.value['client_secret'])
        ;
})
const clearClientInfoDialogState = () => {
    clientInfoDialogState.value['client_id'] = null;
    clientInfoDialogState.value['client_secret'] = null;
}
const saveClientInfo = () => {
    isLoading.value = true;
    OrcaGooglePhotoShare().jsmo.ajax('set-client-info', clientInfoDialogState.value)
        .then(function(response) {
            if (isNotEmpty(response['errors'])) {
                toast.add({ severity: 'error', summary: 'Client Info Update Failed!', detail: response['errors'] });
            } else if (isNotEmpty(response['authUrl'])) {
                toast.add({ severity: 'success', summary: 'Client Info Saved!', detail: 'Redirecting you to the Google Authorization step.', life: 3000 });
                setTimeout(() => {
                    window.location.href = response['authUrl'];
                }, 3000);
            }
        })
        .catch(function(err) {
            debug.value = err;
            isLoading.value = false;
        })
    ;
};
const reAuthorizeClient = () => {
    isLoading.value = true;
    OrcaGooglePhotoShare().jsmo.ajax('get-auth-url', {})
        .then(function(response) {
            if (isNotEmpty(response['errors'])) {
                toast.add({ severity: 'error', summary: 'Unable to Re-Authorize!', detail: response['errors'] });
            } else if (isNotEmpty(response['authUrl'])) {
                toast.add({ severity: 'success', summary: 'Redirecting..', detail: 'Redirecting you to the Google Authorization step.', life: 3000 });
                setTimeout(() => {
                    window.location.href = response['authUrl'];
                }, 3000);
            }
        })
        .catch(function(err) {
            debug.value = err;
            isLoading.value = false;
        })
    ;
}

const clientInfoComplete = () => {
    return config.value
        && isNotEmpty(config.value['client_id'])
        && isNotEmpty(config.value['client_secret'])
        && isNotEmpty(config.value['refresh_token'])
        && isNotEmpty(refreshTokenExpiresHours.value) && refreshTokenExpiresHours.value > 0
    ;
}

/* ALBUM LOGIC */
const canCreateAlbum = computed(() => {
    return clientInfoComplete();
});
const albumNameDialogState = ref({
    "album_name": null
});
const albumNameDialogVisible = ref(false);
const focusAlbumName = () => {
    setTimeout(() => {
        nextTick(() => albumNameModalInput.value.focus());
    }, 500);
}
const editAlbumName = () => {
    if (config) {
        albumNameDialogVisible.value = true;
    }
};
const canSaveAlbumName = computed(() => {
    return isNotEmpty(albumNameDialogState.value)
        && isNotEmpty(albumNameDialogState.value['album_name'])
        ;
})
const clearAlbumNameDialogState = () => {
    albumNameDialogState.value['album_name'] = null;
}
const saveAlbumName = () => {
    isLoading.value = true;
    OrcaGooglePhotoShare().jsmo.ajax('set-album-name', {
        'album_name': albumNameDialogState.value.album_name
    })
        .then(function(response) {
            if (isNotEmpty(response['errors'])) {
                toast.add({ severity: 'error', summary: 'Album Creation Failed!', detail: response['errors'], life: 5000 });
            } else {
                toast.add({ severity: 'success', summary: 'Success!', detail: 'Album Created/Found!', life: 3000 });
                config.value['album_id'] = response;
                config.value['album_name'] = albumNameDialogState.value.album_name;
                albumNameDialogVisible.value = false;
            }
        })
        .catch(function(err) {
            debug.value = err;
        })
        .finally(() => {
            isLoading.value = false;
        });
}

function init() {
    isLoading.value = true;
    OrcaGooglePhotoShare().jsmo.ajax('initialize-config-dashboard', {})
        .then(function(response) {
            config.value = response.config;
            // debug.value = config.value;
        })
        .catch(function(err) {
            debug.value = err;
        })
        .finally(() => {
            isLoading.value = false;
        });
}

const clipboardEnabled = () => {
    return navigator.clipboard && navigator.clipboard.writeText;
}

const copyToClipboard = async (text) => {
    // Use modern Clipboard API if available and a secure context
    if (navigator.clipboard && navigator.clipboard.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            toast.add({ severity: 'success', summary: 'Text copied to clipboard!', detail: text, life: 3000 });
        } catch (err) {
            toast.add({ severity: 'error', summary: 'Failed to copy text using Clipboard API', detail: err, life: 3000 });
        }
    }
}

const scopeStr = computed(() => {
    return config.value['scopes'].join(',\r\n');
})

onMounted(() => {
    if (ModuleUtils.qs_get('error')) {
        toast.add({ severity: 'error', summary: 'Oops! An error occurred.', detail: ModuleUtils.qs_get('error') });
        ModuleUtils.qs_remove('error', true);
    }
    if (ModuleUtils.qs_get('message')) {
        toast.add({ severity: 'info', summary: 'Success!', detail: ModuleUtils.qs_get('message') });
        ModuleUtils.qs_remove('message', true);
    }
});

onBeforeMount(() => {
    init();
});
</script>

<template>
    <div class="projhdr">
        <i class="fa-solid fa-gears">&ZeroWidthSpace;</i>&nbsp;Orca Google Photos Configuration
    </div>
    <Toast />
    <ConfirmDialog></ConfirmDialog>
    <div v-if="isNotEmpty(refreshTokenExpiresHours) && refreshTokenExpiresHours < 0" class="alert alert-danger fs-6 mt-3">
        <div class="fw-bold fs-5">Refresh Token is Expired!</div>
        <hr class="my-2" />
        <div><strong>Expired:</strong> {{ refreshTokenExpiresDisplay }}</div>
        <div class="mt-2">Integration is disabled until a new refresh token is obtained. Click the refresh button (<i class="fa-solid fa-rotate text-danger"></i>) below to re-authorize the application and obtain a new refresh token.</div>
    </div>
    <div class="card module-config" :class="{ modified: isModified }">
        <div class="card-body">
            <template v-if="isEmpty(config)">
                <!-- LOADING PLACEHOLDER -->
                <div class="text-muted lead">LOADING...</div>
            </template>
            <template v-else>
                <h4 class="mb-2 pb-1 border-dark border-bottom border-3">
                    <span>General Configuration</span>
                </h4>
                <!-- base_domain -->
                <div class="form-label fw-bold mb-1 mt-2 d-inline-block">Base Domain</div>
                <div class="input-group">
                    <button v-if="clipboardEnabled" @click="copyToClipboard(config['base_domain'])" class="btn btn-primary"><i class="fa-solid fa-copy"></i></button>
                    <input class="form-control font-monospace text-muted" type="text" v-model="config['base_domain']" readonly disabled />
                </div>
                <!-- redirect_uri -->
                <div class="form-label fw-bold mb-1 mt-2 d-inline-block">Redirect URI</div>
                <div class="input-group">
                    <button v-if="clipboardEnabled" @click="copyToClipboard(config['redirect_uri'])" class="btn btn-primary"><i class="fa-solid fa-copy"></i></button>
                    <input class="form-control font-monospace text-muted" type="text" v-model="config['redirect_uri']" readonly disabled />
                </div>
                <!-- scopes -->
                <div class="form-label fw-bold mt-3 mb-1 d-inline-block">Authorization Scopes</div>
                <div class="input-group">
                    <button v-if="clipboardEnabled" @click="copyToClipboard(scopeStr)" class="btn btn-primary"><i class="fa-solid fa-copy"></i></button>
                    <textarea class="form-control font-monospace text-muted" type="text" v-model="scopeStr" rows="3" readonly disabled />
                </div>

                <hr class="mt-4 mb-3">

                <h4 class="mb-2 pb-1 border-dark border-bottom border-3">
                    <span>Google Cloud Console</span>
                    <a href="https://code.google.com/apis/console" target="_blank" class="fs-5 px-2"><i class="fa-solid fa-up-right-from-square"></i></a>
                </h4>
                <!-- client_id -->
                <div class="form-label fw-bold mb-1 mt-3 d-inline-block">Google Client ID</div>
                <input class="form-control font-monospace text-muted" type="text" v-model="config['client_id']" readonly disabled />
                <!-- client_secret -->
                <div class="form-label fw-bold mt-3 mb-1 d-inline-block">Google Client Secret&nbsp;<i class="fas fa-info-circle text-primary fs-6" v-tooltip="'The full value is never displayed here, to ensure it stays secure.'"></i></div>
                <input class="form-control font-monospace text-muted" type="text" v-model="config['client_secret']" readonly disabled />
                <!-- refresh_token -->
                <div class="form-label fw-bold mt-3 mb-1 d-inline-block">Refresh Token&nbsp;<i class="fas fa-info-circle text-primary fs-6" v-tooltip="'The full value is never displayed here, to ensure it stays secure.'"></i></div>
                <div class="input-group">
                    <button v-if="isNotEmpty(config['refresh_token'])" @click="confirmRefreshToken" class="btn btn-danger"><i class="fa-solid fa-rotate"></i></button>
                    <input class="form-control font-monospace text-muted" type="text" v-model="config['refresh_token']" readonly disabled />
                </div>
                <div v-if="isNotEmpty(refreshTokenExpiresHours)" class="text-secondary fst-italic mt-1">Expires: <strong>{{ refreshTokenExpiresDisplay }}</strong></div>
                <div class="d-flex gap-3 mt-3">
                    <button class="btn btn-sm btn-primary" @click="editClientInfo"><i class="fas fa-pen-to-square"></i>&nbsp;Edit Client Info</button>
                </div>

                <hr class="mt-4 mb-3">

                <h4 class="mb-2 pb-1 border-dark border-bottom border-3">
                    <span>Album Configuration</span>
                </h4>
                <!-- album_name -->
                <div class="form-label fw-bold mb-1 mt-3 d-inline-block">Google Photos Album Name</div>
                <input class="form-control font-monospace text-muted" type="text" v-model="config['album_name']" readonly disabled />
                <!-- album_id -->
                <div class="form-label fw-bold mb-1 mt-3 d-inline-block">Google Photos Album ID</div>
                <input class="form-control font-monospace text-muted" type="text" v-model="config['album_id']" readonly disabled />
                <template v-if="canCreateAlbum">
                    <div class="d-flex gap-3 mt-3">
                        <button class="btn btn-sm btn-primary" @click="editAlbumName"><i class="fas fa-pen-to-square"></i>&nbsp;Edit Album Info</button>
                    </div>
                </template>
                <template v-else>
                    <div class="alert alert-warning mt-3">Cannot Configure the Photo Album until the previous configuration step is complete!</div>
                </template>
            </template>
        </div>
    </div>
    <pre v-if="debug" class="mt-3">{{ debug }}</pre>
    <Dialog modal v-model:visible="clientInfoDialogVisible" header="Edit Client Info" :style="{ width: '50rem' }" position="top"
            @after-hide="clearClientInfoDialogState" @show="focusClientId"
    >
        <div v-if="clientInfoDialogState" class="card">
            <div class="card-header">
                Provide the Client ID and Client Secret values obtained from the Google Cloud Console.
                <div class="p-2 border-start border-5 border-warning bg-warning-subtle mt-3 mb-0 d-flex flex-row gap-2">
                    <strong>NOTE:</strong><div>After clicking Save, any stored Refresh Token will be <strong class="text-danger">erased</strong> and a <strong>new Authorization Request</strong> will be initiated!  Until this request is complete, the module will not be able to push images to your Album.</div>
                </div>
            </div>
            <div class="card-body px-3 pt-2 pb-3">
                <!-- client_id -->
                <div class="form-label fw-bold mb-1 d-inline-block">Google Client ID</div>
                <input ref="client-id-modal" class="form-control font-monospace text-muted" type="text" v-model="clientInfoDialogState['client_id']" />
                <!-- client_secret -->
                <div class="form-label fw-bold mt-3 mb-1 d-inline-block">Google Client Secret</div>
                <input class="form-control font-monospace text-muted" type="text" v-model="clientInfoDialogState['client_secret']" />

                <div v-if="!canSaveClientInfo" class="alert alert-danger mt-3 mb-0">Must provide a value for both fields before saving.</div>
            </div>
        </div>
        <template #footer>
            <button type="button" class="btn btn-primary" @click="saveClientInfo" :disabled="!canSaveClientInfo"><i class="fa-solid fa-floppy-disk"></i>&nbsp;Save</button>
            <button type="button" class="btn btn-outline-danger" @click="clientInfoDialogVisible = false"><i class="fa-solid fa-ban"></i>&nbsp;Cancel</button>
        </template>
    </Dialog>

    <Dialog modal v-model:visible="albumNameDialogVisible" header="Edit Album Name" :style="{ width: '50rem' }" position="top"
            @after-hide="clearAlbumNameDialogState" @show="focusAlbumName"
    >
        <div v-if="albumNameDialogState" class="card">
            <div class="card-header">
                Create an Album by providing an Album Name.  Be descriptive and ensure the name is unique!
                <div class="p-2 border-start border-5 border-warning bg-warning-subtle mt-3 mb-0 d-flex flex-row gap-2">
                    <strong>NOTE:</strong><div>After clicking Save, an attempt will be made to create the Photos Album. If an existing album is found with the same name, that Album ID will be used, instead of creating a new one.</div>
                </div>
            </div>
            <div class="card-body px-3 pt-2 pb-3">
                <!-- album_name -->
                <div class="form-label fw-bold mb-1 d-inline-block">Photos Album Name</div>
                <input ref="album-name-modal" class="form-control font-monospace text-muted" type="text" v-model="albumNameDialogState['album_name']" />
            </div>
        </div>
        <template #footer>
            <button type="button" class="btn btn-primary" @click="saveAlbumName" :disabled="!canSaveAlbumName"><i class="fa-solid fa-floppy-disk"></i>&nbsp;Save</button>
            <button type="button" class="btn btn-outline-danger" @click="albumNameDialogVisible = false"><i class="fa-solid fa-ban"></i>&nbsp;Cancel</button>
        </template>
    </Dialog>

    <Dialog v-model:visible="isLoading"
            modal
            :dismissable-mask="false"
            :closable="false"
            pt:root:class="border-0 bg-transparent shadow-none"
            pt:mask:class="bg-dark bg-opacity-50 backdrop-blur"
    >
        <template #container>
            <div class="d-flex flex-column align-items-center justify-content-center p-3">
                <ProgressSpinner
                    stroke-width="4"
                    animation-duration=".8s"
                    aria-label="Loading content"
                />
            </div>
        </template>
    </Dialog>
</template>

<style>
* {
    --p-tooltip-max-width: 25rem;
}
.p-dialog-header {
    padding-top: 1rem !important;
    padding-bottom: .5rem !important;;
}
.overlay {
    position: fixed !important;
    top: calc(50% - 50px);
    left: calc(50% - 50px);
    z-index: 100; /* this seems to work for me but may need to be higher*/
}
.module-config.card {
    background-color: var(--bs-light);
}
.module-config.card .card-footer {
    background-color: var(--bs-secondary-bg-subtle);
}
.module-config.card.modified {
    background-color: var(--bs-warning-bg-subtle);
}
.module-config.card.modified .card-footer {
    background-color: var(--bs-warning);
}
.btn.btn-outline-primary {
    text-decoration: none;
    color: var(--bs-primary);
}
.btn.btn-outline-primary:hover {
    color: var(--bs-light);
}
</style>