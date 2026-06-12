<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({ employee: { type: Object, required: true } });
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const e = props.employee;

const form = useForm({
    first_name: e.first_name || '', last_name: e.last_name || '', email: e.email || '', phone: e.phone || '',
    date_of_birth: e.date_of_birth?.substring(0, 10) || '', gender: e.gender || '',
    designation: e.designation || '', department: e.department || '',
    date_of_joining: e.date_of_joining?.substring(0, 10) || '', date_of_leaving: e.date_of_leaving?.substring(0, 10) || '',
    employment_type: e.employment_type || 'full_time', status: e.status || 'active',
    ctc_annual: e.ctc_annual || '', basic_salary: e.basic_salary || '', hra: e.hra || '',
    special_allowance: e.special_allowance || '', other_allowance: e.other_allowance || '',
    bank_name: e.bank_name || '', bank_account_number: e.bank_account_number || '', bank_ifsc: e.bank_ifsc || '',
    pan_number: e.pan_number || '', aadhaar_number: e.aadhaar_number || '', uan_number: e.uan_number || '', esi_number: e.esi_number || '',
    pf_applicable: e.pf_applicable ?? true, esi_applicable: e.esi_applicable ?? true, pf_number: e.pf_number || '',
    address: e.address || '', city: e.city || '', state: e.state || '', pincode: e.pincode || '',
    emergency_contact_name: e.emergency_contact_name || '', emergency_contact_phone: e.emergency_contact_phone || '',
    emergency_contact_relation: e.emergency_contact_relation || '', notes: e.notes || '',
});

function submit() { form.put(route('tenant.hr.employees.update', { tenant: slug, id: e.id })); }
</script>

<template>
<Head :title="'Edit ' + employee.first_name" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.hr.employees.show', { tenant: slug, id: employee.id })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
        <h2 class="text-[20px] font-bold text-white">Edit {{ employee.first_name }} {{ employee.last_name }}</h2>
        <span class="pill pill-info font-mono">{{ employee.employee_id }}</span>
    </div>

    <form @submit.prevent="submit" class="max-w-3xl space-y-5">
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Personal</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="heyd2c-label">First Name *</label><input v-model="form.first_name" class="heyd2c-input" /><div v-if="form.errors.first_name" class="mt-1 text-[11px] text-rose">{{ form.errors.first_name }}</div></div>
                <div><label class="heyd2c-label">Last Name</label><input v-model="form.last_name" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Email</label><input v-model="form.email" type="email" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Phone</label><input v-model="form.phone" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Date of Birth</label><input v-model="form.date_of_birth" type="date" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Gender</label><select v-model="form.gender" class="heyd2c-input"><option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
            </div>
        </div>
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Employment</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="heyd2c-label">Designation</label><input v-model="form.designation" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Department</label><input v-model="form.department" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Date of Joining</label><input v-model="form.date_of_joining" type="date" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Date of Leaving</label><input v-model="form.date_of_leaving" type="date" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Type</label><select v-model="form.employment_type" class="heyd2c-input"><option value="full_time">Full Time</option><option value="part_time">Part Time</option><option value="contract">Contract</option><option value="intern">Intern</option></select></div>
                <div><label class="heyd2c-label">Status</label><select v-model="form.status" class="heyd2c-input"><option value="active">Active</option><option value="on_notice">On Notice</option><option value="terminated">Terminated</option><option value="resigned">Resigned</option></select></div>
            </div>
        </div>
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Compensation (Monthly)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="heyd2c-label">Annual CTC</label><input v-model="form.ctc_annual" type="number" step="0.01" class="heyd2c-input" /></div>
                <div></div>
                <div><label class="heyd2c-label">Basic Salary</label><input v-model="form.basic_salary" type="number" step="0.01" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">HRA</label><input v-model="form.hra" type="number" step="0.01" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Special Allowance</label><input v-model="form.special_allowance" type="number" step="0.01" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Other Allowance</label><input v-model="form.other_allowance" type="number" step="0.01" class="heyd2c-input" /></div>
            </div>
        </div>
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Bank & Statutory</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="heyd2c-label">Bank Name</label><input v-model="form.bank_name" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Account No.</label><input v-model="form.bank_account_number" class="heyd2c-input font-mono" /></div>
                <div><label class="heyd2c-label">IFSC</label><input v-model="form.bank_ifsc" class="heyd2c-input font-mono" maxlength="11" /></div>
                <div><label class="heyd2c-label">PAN</label><input v-model="form.pan_number" class="heyd2c-input font-mono" maxlength="10" /></div>
                <div><label class="heyd2c-label">Aadhaar</label><input v-model="form.aadhaar_number" class="heyd2c-input font-mono" maxlength="12" /></div>
                <div><label class="heyd2c-label">UAN (PF)</label><input v-model="form.uan_number" class="heyd2c-input font-mono" /></div>
                <div><label class="heyd2c-label">PF Member ID</label><input v-model="form.pf_number" class="heyd2c-input font-mono" placeholder="MH/MUM/12345/001" /></div>
                <div><label class="heyd2c-label">ESI Number</label><input v-model="form.esi_number" class="heyd2c-input font-mono" /></div>
            </div>
            <div class="mt-4 flex items-center gap-6">
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.pf_applicable" type="checkbox" class="accent-brand-600" /> PF Applicable
                </label>
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.esi_applicable" type="checkbox" class="accent-brand-600" /> ESI Applicable
                </label>
            </div>
        </div>
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Address</h3>
            <div class="grid grid-cols-1 gap-3">
                <div><label class="heyd2c-label">Address</label><textarea v-model="form.address" class="heyd2c-input" rows="2"></textarea></div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">City</label><input v-model="form.city" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">State</label><input v-model="form.state" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Pincode</label><input v-model="form.pincode" class="heyd2c-input" maxlength="6" /></div>
                </div>
            </div>
        </div>
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Emergency Contact</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="heyd2c-label">Name</label><input v-model="form.emergency_contact_name" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Phone</label><input v-model="form.emergency_contact_phone" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Relation</label><input v-model="form.emergency_contact_relation" class="heyd2c-input" /></div>
            </div>
        </div>
        <div><label class="heyd2c-label">Notes</label><textarea v-model="form.notes" class="heyd2c-input" rows="2"></textarea></div>
        <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Update Employee' }}</button>
    </form>
</TenantLayout>
</template>
