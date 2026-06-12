<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    nextEmployeeId: { type: String, default: 'EMP-001' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const form = useForm({
    employee_id: props.nextEmployeeId,
    first_name: '', last_name: '', email: '', phone: '',
    date_of_birth: '', gender: '',
    designation: '', department: '', date_of_joining: '',
    employment_type: 'full_time', status: 'active',
    ctc_annual: '', basic_salary: '', hra: '', special_allowance: '', other_allowance: '',
    bank_name: '', bank_account_number: '', bank_ifsc: '',
    pan_number: '', aadhaar_number: '', uan_number: '', esi_number: '',
    pf_applicable: true, esi_applicable: true, pf_number: '',
    address: '', city: '', state: '', pincode: '',
    emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relation: '',
    notes: '',
});

function submit() {
    form.post(route('tenant.hr.employees.store', { tenant: slug }));
}
</script>

<template>
<Head title="Add Employee" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.hr.employees', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
        <h2 class="text-[20px] font-bold text-white">Add Employee</h2>
    </div>

    <form @submit.prevent="submit" class="max-w-3xl space-y-5">
        <!-- Personal Info -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Personal Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Employee ID</label>
                    <input v-model="form.employee_id" class="heyd2c-input font-mono" />
                    <div v-if="form.errors.employee_id" class="mt-1 text-[11px] text-rose">{{ form.errors.employee_id }}</div>
                </div>
                <div></div>
                <div>
                    <label class="heyd2c-label">First Name *</label>
                    <input v-model="form.first_name" class="heyd2c-input" />
                    <div v-if="form.errors.first_name" class="mt-1 text-[11px] text-rose">{{ form.errors.first_name }}</div>
                </div>
                <div>
                    <label class="heyd2c-label">Last Name</label>
                    <input v-model="form.last_name" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Email</label>
                    <input v-model="form.email" type="email" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Phone</label>
                    <input v-model="form.phone" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Date of Birth</label>
                    <input v-model="form.date_of_birth" type="date" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Gender</label>
                    <select v-model="form.gender" class="heyd2c-input">
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Employment Details -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Employment Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Designation</label>
                    <input v-model="form.designation" class="heyd2c-input" placeholder="e.g. Software Engineer" />
                </div>
                <div>
                    <label class="heyd2c-label">Department</label>
                    <input v-model="form.department" class="heyd2c-input" placeholder="e.g. Engineering" />
                </div>
                <div>
                    <label class="heyd2c-label">Date of Joining</label>
                    <input v-model="form.date_of_joining" type="date" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Employment Type</label>
                    <select v-model="form.employment_type" class="heyd2c-input">
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Compensation -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Compensation (Monthly)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Annual CTC</label>
                    <input v-model="form.ctc_annual" type="number" step="0.01" class="heyd2c-input" placeholder="₹" />
                </div>
                <div></div>
                <div>
                    <label class="heyd2c-label">Basic Salary</label>
                    <input v-model="form.basic_salary" type="number" step="0.01" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">HRA</label>
                    <input v-model="form.hra" type="number" step="0.01" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Special Allowance</label>
                    <input v-model="form.special_allowance" type="number" step="0.01" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Other Allowance</label>
                    <input v-model="form.other_allowance" type="number" step="0.01" class="heyd2c-input" />
                </div>
            </div>
        </div>

        <!-- Bank & Statutory -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Bank & Statutory</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Bank Name</label>
                    <input v-model="form.bank_name" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Account Number</label>
                    <input v-model="form.bank_account_number" class="heyd2c-input font-mono" />
                </div>
                <div>
                    <label class="heyd2c-label">IFSC Code</label>
                    <input v-model="form.bank_ifsc" class="heyd2c-input font-mono" maxlength="11" />
                </div>
                <div>
                    <label class="heyd2c-label">PAN Number</label>
                    <input v-model="form.pan_number" class="heyd2c-input font-mono" maxlength="10" />
                </div>
                <div>
                    <label class="heyd2c-label">Aadhaar Number</label>
                    <input v-model="form.aadhaar_number" class="heyd2c-input font-mono" maxlength="12" />
                </div>
                <div>
                    <label class="heyd2c-label">UAN (PF)</label>
                    <input v-model="form.uan_number" class="heyd2c-input font-mono" />
                </div>
                <div>
                    <label class="heyd2c-label">PF Member ID</label>
                    <input v-model="form.pf_number" class="heyd2c-input font-mono" placeholder="MH/MUM/12345/001" />
                </div>
                <div>
                    <label class="heyd2c-label">ESI Number</label>
                    <input v-model="form.esi_number" class="heyd2c-input font-mono" />
                </div>
            </div>
            <div class="mt-4 flex items-center gap-6">
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.pf_applicable" type="checkbox" class="accent-brand-600" />
                    PF Applicable
                </label>
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.esi_applicable" type="checkbox" class="accent-brand-600" />
                    ESI Applicable
                </label>
            </div>
        </div>

        <!-- Address -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Address</h3>
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="heyd2c-label">Address</label>
                    <textarea v-model="form.address" class="heyd2c-input" rows="2"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="heyd2c-label">City</label>
                        <input v-model="form.city" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">State</label>
                        <input v-model="form.state" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Pincode</label>
                        <input v-model="form.pincode" class="heyd2c-input" maxlength="6" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Emergency Contact</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="heyd2c-label">Name</label>
                    <input v-model="form.emergency_contact_name" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Phone</label>
                    <input v-model="form.emergency_contact_phone" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Relation</label>
                    <input v-model="form.emergency_contact_relation" class="heyd2c-input" placeholder="e.g. Spouse, Parent" />
                </div>
            </div>
        </div>

        <div>
            <label class="heyd2c-label">Notes</label>
            <textarea v-model="form.notes" class="heyd2c-input" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Add Employee' }}
        </button>
    </form>
</TenantLayout>
</template>
