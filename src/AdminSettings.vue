<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('registration', 'Registration settings')">
		<NcCheckboxRadioSwitch
			v-model="adminApproval"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Require administrator approval') }}
		</NcCheckboxRadioSwitch>

		<p><em>{{ t('registration', 'Enabling "administrator approval" will prevent registrations from mobile and desktop clients to complete as the credentials cannot be verified by the client until the user was enabled.') }}</em></p>

		<div>
			<div class="margin-top">
				<label for="registered_user_group">
					{{ t('registration', 'Registered users default group') }}
				</label>
			</div>
			<NcSelect
				id="registered_user_group"
				v-model="registeredUserGroup"
				:placeholder="t('registration', 'Select group')"
				:options="groups"
				:disabled="loading"
				:searchable="true"
				:tagWidth="60"
				:loading="loadingGroups"
				:closeOnSelect="false"
				label="displayname"
				@search="searchGroup"
				@update:modelValue="saveData" />
		</div>
	</NcSettingsSection>

	<NcSettingsSection :name="t('registration', 'Email settings')">
		<NcCheckboxRadioSwitch
			v-model="emailIsOptional"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Email is optional') }}
		</NcCheckboxRadioSwitch>

		<div class="domain-list">
			<div class="domain-list__header" aria-hidden="true">
				<span>{{ domainListLabel }}</span>
				<span>{{ t('registration', 'Domain group') }}</span>
			</div>
			<div
				v-for="(domainRow, index) in allowedDomainRows"
				:key="domainRow.id"
				class="domain-list__row">
				<NcTextField
					v-model="domainRow.domain"
					:label="domainListLabel"
					:labelVisible="false"
					:aria-label="domainListLabel"
					:disabled="loading"
					placeholder="nextcloud.com"
					@update:modelValue="debounceSavingSlow" />
				<NcSelect
					v-model="domainRow.group"
					:placeholder="t('registration', 'Select group')"
					:options="groups"
					:disabled="loading"
					:searchable="true"
					:tagWidth="60"
					:loading="loadingGroups"
					:closeOnSelect="false"
					label="displayname"
					@search="searchGroup"
					@update:modelValue="saveData" />
				<button
					type="button"
					class="domain-list__remove"
					:aria-label="t('registration', 'Remove domain')"
					:disabled="loading || allowedDomainRows.length === 1"
					@click="removeDomainRow(index)">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<button
				type="button"
				class="domain-list__add"
				:disabled="loading"
				@click="addDomainRow">
				<span aria-hidden="true">+</span>
				{{ t('registration', 'Add domain') }}
			</button>
		</div>

		<NcCheckboxRadioSwitch
			v-model="domainsIsBlocklist"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Block listed email domains instead of allowing them') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			v-model="showDomains"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ showDomainListLabel }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			v-if="!emailIsOptional"
			v-model="disableEmailVerification"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Disable email verification') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>

	<NcSettingsSection :name="t('registration', 'User settings')">
		<NcCheckboxRadioSwitch
			v-if="!emailIsOptional"
			v-model="emailIsLogin"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Force email as login name') }}
		</NcCheckboxRadioSwitch>
		<template v-if="!emailIsLogin">
			<p>
				<label for="username_policy_regex">{{ t('registration', 'Login name policy') }}</label>
				<input
					id="username_policy_regex"
					v-model="usernamePolicyRegex"
					type="text"
					name="username_policy_regex"
					:disabled="loading"
					placeholder="E.g.: /^[a-z-]+\.[a-z-]+$/"
					:aria-label="t('registration', 'Regular expression to validate login names')"
					@update:modelValue="debounceSavingSlow">
			</p>
			<em>{{ t('registration', 'If configured, login names will be validated through the regular expression. If the validation fails the user is prompted with a generic error. Make sure your regex is working correctly.') }}</em>
		</template>

		<NcCheckboxRadioSwitch
			v-model="showFullname"
			:disabled="loading"
			type="switch"
			@update:modelValue="saveData">
			{{ t('registration', 'Show full name field') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			v-if="showFullname"
			v-model="enforceFullname"
			class="indent"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Enforce full name field') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			v-model="showPhone"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Show phone field') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			v-if="showPhone"
			v-model="enforcePhone"
			class="indent"
			type="switch"
			:disabled="loading"
			@update:modelValue="saveData">
			{{ t('registration', 'Enforce phone field') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>

	<NcSettingsSection
		:name="t('registration', 'User instructions')"
		:description="t('registration', 'Caution: The user instructions will not be translated and will therefore be displayed as configured below for all users regardless of their actual language.')">
		<h3>{{ t('registration', 'Registration form instructions') }}</h3>
		<input
			v-model="additionalHint"
			type="text"
			name="additional_hint"
			:disabled="loading"
			:placeholder="t('registration', `Please create your username following the scheme 'firstname.lastname'.`)"
			:aria-label="t('registration', 'A short message that is shown to the user in the registration process.')"
			@update:modelValue="debounceSavingSlow">
		<p><em>{{ t('registration', 'Add additional user instructions (e.g. for choosing their login name). If configured the text is displayed in the account creation step of the registration process.') }}</em></p>

		<h3>{{ t('registration', 'Verification email instructions') }}</h3>
		<input
			v-model="emailVerificationHint"
			type="text"
			name="email_verification_hint"
			:disabled="loading"
			:placeholder="t('registration', `Please create your username following the scheme 'firstname.lastname'.`)"
			:aria-label="t('registration', 'A short message that is shown to the user in the verification email.')"
			@update:modelValue="debounceSavingSlow">
		<p><em>{{ t('registration', 'Add additional user instructions (e.g. for choosing their login name). If configured the text is embedded in the verification-email.') }}</em></p>
	</NcSettingsSection>
</template>

<script lang="ts" setup>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, onMounted, ref } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

// Styles
import '@nextcloud/dialogs/style.css'

type Group = {
	id: string
	displayname: string
	usercount: number
	disabled: number
	canAdd: boolean
	canRemove: boolean
}

type DomainRow = {
	id: number
	domain: string
	group: Group | null
}

const loading = ref(false)
const loadingGroups = ref(false)
const groups = ref<Group[]>([])
const saveNotification = ref<unknown>(null)
const adminApproval = ref<boolean>(loadState<boolean>('registration', 'admin_approval_required'))
const registeredUserGroup = ref<Group>(loadState<Group>('registration', 'registered_user_group'))
const initialAllowedDomains = loadState<string>('registration', 'allowed_domains') || ''
const initialDomainGroups = loadState<Record<string, Group>>('registration', 'domain_groups') || {}
const nextDomainRowId = ref(0)
const allowedDomainRows = ref<DomainRow[]>(createDomainRows(initialAllowedDomains, initialDomainGroups))
const domainsIsBlocklist = ref<boolean>(loadState<boolean>('registration', 'domains_is_blocklist'))
const showDomains = ref<boolean>(loadState<boolean>('registration', 'show_domains'))
const emailIsOptional = ref<boolean>(loadState<boolean>('registration', 'email_is_optional'))
const disableEmailVerification = ref<boolean>(loadState<boolean>('registration', 'disable_email_verification'))
const emailIsLogin = ref<boolean>(loadState<boolean>('registration', 'email_is_login'))
const usernamePolicyRegex = ref<string>(loadState('registration', 'username_policy_regex'))
const showFullname = ref<boolean>(loadState<boolean>('registration', 'show_fullname'))
const enforceFullname = ref<boolean>(loadState<boolean>('registration', 'enforce_fullname'))
const showPhone = ref<boolean>(loadState<boolean>('registration', 'show_phone'))
const enforcePhone = ref<boolean>(loadState<boolean>('registration', 'enforce_phone'))
const additionalHint = ref(loadState('registration', 'additional_hint'))
const emailVerificationHint = ref(loadState('registration', 'email_verification_hint'))

const domainListLabel = computed(() => {
	if (domainsIsBlocklist.value) {
		return t('registration', 'Blocked email domains')
	}
	return t('registration', 'Allowed email domains')
})

const showDomainListLabel = computed(() => {
	if (domainsIsBlocklist.value) {
		return t('registration', 'Show the blocked email domains to users')
	}
	return t('registration', 'Show the allowed email domains to users')
})

const debounceSavingSlow = debounce(function() {
	saveData()
}, 2000)

function createDomainRows(domains: string, domainGroups: Record<string, Group>): DomainRow[] {
	const rows = parseDomains(domains).map((domain) => {
		const normalizedDomain = normalizeDomain(domain)
		return {
			id: nextDomainRowId.value++,
			domain,
			group: domainGroups[normalizedDomain] ?? null,
		}
	})

	if (rows.length === 0) {
		rows.push(createEmptyDomainRow())
	}

	return rows
}

function createEmptyDomainRow(): DomainRow {
	return {
		id: nextDomainRowId.value++,
		domain: '',
		group: null,
	}
}

function parseDomains(domains: string): string[] {
	return domains
		.split(';')
		.map((domain) => domain.trim())
		.filter((domain) => domain !== '')
}

function normalizeDomain(domain: string): string {
	return domain.trim().toLowerCase()
}

function getDomainRowsPayload(): DomainRow[] {
	const seenDomains = new Set<string>()
	const rows: DomainRow[] = []

	for (const row of allowedDomainRows.value) {
		const domain = normalizeDomain(row.domain)
		if (domain === '' || seenDomains.has(domain)) {
			continue
		}

		seenDomains.add(domain)
		rows.push({
			...row,
			domain,
		})
	}

	return rows
}

const allowedDomainsPayload = computed(() => {
	return getDomainRowsPayload()
		.map((row) => row.domain)
		.join(';')
})

const domainGroupsPayload = computed(() => {
	const domainGroups: Record<string, string> = {}
	for (const row of getDomainRowsPayload()) {
		if (row.group?.id) {
			domainGroups[row.domain] = row.group.id
		}
	}
	return domainGroups
})

function addDomainRow() {
	allowedDomainRows.value.push(createEmptyDomainRow())
}

function removeDomainRow(index: number) {
	allowedDomainRows.value.splice(index, 1)
	if (allowedDomainRows.value.length === 0) {
		allowedDomainRows.value.push(createEmptyDomainRow())
	}
	saveData()
}

/**
 *
 */
async function saveData() {
	loading.value = true
	if (saveNotification.value) {
		// @ts-expect-error-next-line
		await saveNotification.value.hideToast()
	}

	try {
		const response = await axios.post(generateUrl('/apps/registration/settings'), {
			admin_approval_required: adminApproval.value,
			registered_user_group: registeredUserGroup.value?.id,
			allowed_domains: allowedDomainsPayload.value,
			domain_groups: JSON.stringify(domainGroupsPayload.value),
			domains_is_blocklist: domainsIsBlocklist.value,
			show_domains: showDomains.value,
			email_is_optional: emailIsOptional.value,
			disable_email_verification: emailIsOptional.value || disableEmailVerification.value,
			email_is_login: !emailIsOptional.value && emailIsLogin.value,
			username_policy_regex: usernamePolicyRegex.value,
			show_fullname: showFullname.value,
			enforce_fullname: enforceFullname.value,
			show_phone: showPhone.value,
			enforce_phone: enforcePhone.value,
			additional_hint: additionalHint.value,
			email_verification_hint: emailVerificationHint.value,
		})

		if (response?.data?.status === 'success' && response?.data?.data?.message) {
			saveNotification.value = showSuccess(response.data.data.message)
		} else if (response?.data?.data?.message) {
			saveNotification.value = showError(response.data.data.message)
		} else {
			saveNotification.value = showError(t('registration', 'An error occurred while saving the settings'))
		}
	} catch (e) {
		if (e.response?.data?.data?.message) {
			saveNotification.value = showError(e.response.data.data.message)
		} else {
			saveNotification.value = showError(t('registration', 'An error occurred while saving the settings'))
			console.error(e)
		}
	}

	loading.value = false
}

const searchGroup = debounce(async function(query) {
	loadingGroups.value = true
	try {
		const response = await axios.get(generateOcsUrl('cloud/groups/details'), {
			params: {
				search: query,
				limit: 20,
				offset: 0,
			},
		})
		groups.value = response.data.ocs.data.groups.sort(function(a: Group, b: Group) {
			return a.displayname.localeCompare(b.displayname)
		})
	} catch (err) {
		console.error('Could not fetch groups', err)
	} finally {
		loadingGroups.value = false
	}
}, 500)

onMounted(() => {
	searchGroup('')
})
</script>

<style scoped lang="scss">

p {
	label {
		display: block;
	}

	margin-top: 15px;
}

.indent {
	padding-left: 28px;
}

.margin-top {
	margin-top: 1rem;
}

input,
select {
	width: 33%;
	min-width: 250px;
}

h3 {
	margin-top: 25px;
}

.domain-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 760px;
	margin-top: 12px;
}

.domain-list__header,
.domain-list__row {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) var(--default-clickable-area);
	gap: 12px;
	align-items: center;
}

.domain-list__header {
	padding-inline: 0 var(--default-clickable-area);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.domain-list__row :deep(.input-field),
.domain-list__row :deep(.select) {
	width: 100%;
	min-width: 0;
}

.domain-list__remove {
	display: flex;
	align-items: center;
	justify-content: center;
	width: var(--default-clickable-area);
	height: var(--default-clickable-area);
	border: 0;
	border-radius: var(--border-radius);
	background-color: transparent;
	color: var(--color-main-text);
	font-size: 22px;
	line-height: 1;
	justify-self: end;
}

.domain-list__add {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	align-self: start;
	margin-top: 4px;
}

@media (max-width: 700px) {
	.domain-list__header {
		display: none;
	}

	.domain-list__row {
		grid-template-columns: minmax(0, 1fr) var(--default-clickable-area);
	}

	.domain-list__row :deep(.select) {
		grid-column: 1 / 2;
	}

	.domain-list__remove {
		grid-column: 2 / 3;
		grid-row: 1 / 3;
		align-self: center;
	}
}
</style>
