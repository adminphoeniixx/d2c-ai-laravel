<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Settings2, Clock, MapPin, Scan, Calendar } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    schedules: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const form = useForm({
    shift_start: props.settings.shift_start?.substring(0, 5) || '09:00',
    shift_end: props.settings.shift_end?.substring(0, 5) || '18:00',
    standard_hours: props.settings.standard_hours ?? 8,
    lunch_break_hours: props.settings.lunch_break_hours ?? 1,
    late_threshold_minutes: props.settings.late_threshold_minutes ?? 15,
    half_day_threshold_minutes: props.settings.half_day_threshold_minutes ?? 120,
    late_penalty_type: props.settings.late_penalty_type || 'fixed',
    late_penalty_amount: props.settings.late_penalty_amount ?? 0,
    late_penalty_per_minute: props.settings.late_penalty_per_minute ?? 0,
    late_grace_count: props.settings.late_grace_count ?? 3,
    overtime_rate_multiplier: props.settings.overtime_rate_multiplier ?? 1.5,
    overtime_min_minutes: props.settings.overtime_min_minutes ?? 30,
    geo_fence_enabled: props.settings.geo_fence_enabled ?? false,
    geo_fence_latitude: props.settings.geo_fence_latitude || '',
    geo_fence_longitude: props.settings.geo_fence_longitude || '',
    geo_fence_radius_meters: props.settings.geo_fence_radius_meters ?? 200,
    face_recognition_required: props.settings.face_recognition_required ?? false,
    auto_mark_absent: props.settings.auto_mark_absent ?? true,
    auto_absent_after: props.settings.auto_absent_after?.substring(0, 5) || '12:00',
});

const scheduleForm = useForm({
    schedules: props.schedules.map(s => ({
        day_of_week: s.day_of_week,
        is_working_day: s.is_working_day,
        shift_start: s.shift_start?.substring(0, 5) || '',
        shift_end: s.shift_end?.substring(0, 5) || '',
    })),
});

function saveSettings() {
    form.put(route('tenant.hr.attendance.settings.update', { tenant: slug }));
}

function saveSchedule() {
    scheduleForm.put(route('tenant.hr.attendance.schedule.update', { tenant: slug }));
}

const activeTab = ref('shift');
const tabs = [
    { id: 'shift', icon: Clock, label: 'Shift & Hours' },
    { id: 'late', icon: Settings2, label: 'Late & Penalty' },
    { id: 'overtime', icon: Clock, label: 'Overtime' },
    { id: 'geo', icon: MapPin, label: 'Geo-Fence & Face' },
    { id: 'schedule', icon: Calendar, label: 'Work Schedule' },
];
</script>

<template>
<Head title="Attendance Settings" />
<TenantLayout>
    <div class="max-w-3xl">
        <div class="mb-5">
            <h2 class="text-[20px] font-bold text-white">Attendance Settings</h2>
            <p class="text-[12px] text-ink-3 mt-1">Configure shift timings, late policy, overtime rules, and work schedule</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-5 overflow-x-auto pb-1">
            <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-[12px] font-medium whitespace-nowrap transition cursor-pointer"
                :class="activeTab === t.id ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink-2 border border-transparent'">
                <component :is="t.icon" :size="14" /> {{ t.label }}
            </button>
        </div>

        <!-- Shift & Hours -->
        <form v-if="activeTab === 'shift'" @submit.prevent="saveSettings" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4">Default Shift Timing</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="heyd2c-label">Shift Start</label>
                        <input v-model="form.shift_start" type="time" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Shift End</label>
                        <input v-model="form.shift_end" type="time" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Standard Hours</label>
                        <input v-model="form.standard_hours" type="number" step="0.5" min="1" max="24" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Lunch Break (hours)</label>
                        <input v-model="form.lunch_break_hours" type="number" step="0.25" min="0" max="3" class="heyd2c-input" />
                    </div>
                </div>
                <div class="mt-4 rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.auto_mark_absent" type="checkbox" class="accent-brand-600" />
                            Auto-mark absent if no check-in
                        </label>
                        <div v-if="form.auto_mark_absent" class="flex items-center gap-2">
                            <span class="text-[11px] text-ink-3">After</span>
                            <input v-model="form.auto_absent_after" type="time" class="heyd2c-input w-28 !py-1" />
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Shift Settings' }}
            </button>
        </form>

        <!-- Late & Penalty -->
        <form v-if="activeTab === 'late'" @submit.prevent="saveSettings" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4">Late Policy</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="heyd2c-label">Late After (minutes)</label>
                        <input v-model="form.late_threshold_minutes" type="number" min="0" class="heyd2c-input" />
                        <p class="mt-1 text-[10px] text-ink-3">Employee marked late after these many minutes past shift start</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Half-Day After (minutes)</label>
                        <input v-model="form.half_day_threshold_minutes" type="number" min="0" class="heyd2c-input" />
                        <p class="mt-1 text-[10px] text-ink-3">Marked half-day if late by this many minutes</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Grace Count / Month</label>
                        <input v-model="form.late_grace_count" type="number" min="0" max="31" class="heyd2c-input" />
                        <p class="mt-1 text-[10px] text-ink-3">Free lates before penalty kicks in</p>
                    </div>
                </div>

                <h4 class="text-[13px] font-semibold text-white mt-5 mb-3">Penalty Configuration</h4>
                <div class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Penalty Type</label>
                        <select v-model="form.late_penalty_type" class="heyd2c-input">
                            <option value="none">No Penalty</option>
                            <option value="fixed">Fixed Amount per Late</option>
                            <option value="per_minute">Per Minute Late</option>
                            <option value="per_day_salary">Half-Day Salary Deduction</option>
                        </select>
                    </div>
                    <div v-if="form.late_penalty_type === 'fixed'">
                        <label class="heyd2c-label">Fixed Penalty Amount (₹)</label>
                        <input v-model="form.late_penalty_amount" type="number" step="1" min="0" class="heyd2c-input" />
                    </div>
                    <div v-if="form.late_penalty_type === 'per_minute'">
                        <label class="heyd2c-label">₹ per Minute Late</label>
                        <input v-model="form.late_penalty_per_minute" type="number" step="0.5" min="0" class="heyd2c-input" />
                    </div>
                    <div v-if="form.late_penalty_type === 'per_day_salary'" class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <p class="text-[12px] text-ink-3">Half of daily salary (monthly ÷ 26 ÷ 2) will be deducted for each late after grace count.</p>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Late Policy' }}
            </button>
        </form>

        <!-- Overtime -->
        <form v-if="activeTab === 'overtime'" @submit.prevent="saveSettings" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4">Overtime Rules</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="heyd2c-label">Overtime Rate Multiplier</label>
                        <input v-model="form.overtime_rate_multiplier" type="number" step="0.25" min="1" max="5" class="heyd2c-input" />
                        <p class="mt-1 text-[10px] text-ink-3">1.5x means OT hours paid at 150% of normal rate</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Minimum OT Minutes</label>
                        <input v-model="form.overtime_min_minutes" type="number" min="0" class="heyd2c-input" />
                        <p class="mt-1 text-[10px] text-ink-3">Work after shift end only counts as OT if above this threshold</p>
                    </div>
                </div>
                <div class="mt-4 rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                    <p class="text-[12px] text-ink-3">Overtime is calculated as: time worked after <strong class="text-ink-2">shift end</strong>. Only counted if above the minimum threshold.</p>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Overtime Rules' }}
            </button>
        </form>

        <!-- Geo-Fence & Face -->
        <form v-if="activeTab === 'geo'" @submit.prevent="saveSettings" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4">Location & Face Recognition</h3>

                <div class="mb-5">
                    <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none mb-3">
                        <input v-model="form.geo_fence_enabled" type="checkbox" class="accent-brand-600" />
                        <MapPin :size="14" /> Enable Geo-Fence
                    </label>
                    <div v-if="form.geo_fence_enabled" class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="heyd2c-label">Latitude</label>
                            <input v-model="form.geo_fence_latitude" type="number" step="0.0000001" class="heyd2c-input font-mono text-[12px]" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Longitude</label>
                            <input v-model="form.geo_fence_longitude" type="number" step="0.0000001" class="heyd2c-input font-mono text-[12px]" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Radius (meters)</label>
                            <input v-model="form.geo_fence_radius_meters" type="number" min="10" class="heyd2c-input" />
                        </div>
                    </div>
                    <p v-if="form.geo_fence_enabled" class="mt-2 text-[10px] text-ink-3">Employee must be within this radius to check in/out via app</p>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                        <input v-model="form.face_recognition_required" type="checkbox" class="accent-brand-600" />
                        <Scan :size="14" /> Require Face Recognition for Check-in
                    </label>
                    <p class="mt-1 text-[10px] text-ink-3 ml-6">Employee must verify face before attendance is recorded</p>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Location Settings' }}
            </button>
        </form>

        <!-- Work Schedule -->
        <form v-if="activeTab === 'schedule'" @submit.prevent="saveSchedule" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4">Weekly Work Schedule</h3>
                <p class="text-[12px] text-ink-3 mb-4">Configure working/non-working days and optional shift overrides per day</p>

                <div class="space-y-2">
                    <div v-for="(s, i) in scheduleForm.schedules" :key="s.day_of_week"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition"
                        :class="s.is_working_day ? 'bg-brand-600/5 border border-brand-600/20' : 'bg-bg-3 border border-frost-1'">
                        <label class="flex items-center gap-2 w-36 cursor-pointer select-none">
                            <input v-model="s.is_working_day" type="checkbox" class="accent-brand-600" />
                            <span class="text-[13px] font-medium" :class="s.is_working_day ? 'text-white' : 'text-ink-3'">{{ dayNames[s.day_of_week] }}</span>
                        </label>
                        <template v-if="s.is_working_day">
                            <input v-model="s.shift_start" type="time" class="heyd2c-input !py-1 w-28" placeholder="Default" />
                            <span class="text-[11px] text-ink-3">to</span>
                            <input v-model="s.shift_end" type="time" class="heyd2c-input !py-1 w-28" placeholder="Default" />
                            <span class="text-[10px] text-ink-3">Leave blank for default shift</span>
                        </template>
                        <span v-else class="text-[12px] text-ink-3 italic">Non-working day</span>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="scheduleForm.processing">
                {{ scheduleForm.processing ? 'Saving…' : 'Save Work Schedule' }}
            </button>
        </form>
    </div>
</TenantLayout>
</template>
