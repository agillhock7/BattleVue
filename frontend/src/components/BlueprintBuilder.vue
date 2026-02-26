<template>
  <div class="panel col">
    <div class="row" style="justify-content: space-between; align-items: center;">
      <h3 style="margin: 0;">Blueprint Builder</h3>
      <span class="muted">{{ local.modules.length }}/12 modules</span>
    </div>

    <label>
      Name
      <input v-model="local.name" placeholder="Vanguard Mk1" />
    </label>

    <div class="card col">
      <strong>Chassis</strong>
      <div class="row chip-row" v-if="chassisOptions.length">
        <button
          v-for="chassis in chassisOptions"
          :key="chassis.slug"
          type="button"
          class="ghost chip"
          :class="{ active: local.chassis === chassis.slug }"
          @click="local.chassis = chassis.slug"
        >
          {{ chassis.name }}
        </button>
      </div>
      <input v-model="local.chassis" placeholder="chassis-starter" />
    </div>

    <label>
      Lane Preference
      <select v-model="local.lane_pref">
        <option value="left">Left</option>
        <option value="mid">Mid</option>
        <option value="right">Right</option>
        <option value="adaptive">Adaptive</option>
      </select>
    </label>

    <div class="card col">
      <div class="row" style="justify-content: space-between; align-items: center;">
        <strong>Core Stats</strong>
        <div class="row chip-row">
          <button type="button" class="ghost chip" @click="applyStatPreset('balanced')">Balanced</button>
          <button type="button" class="ghost chip" @click="applyStatPreset('tank')">Tank</button>
          <button type="button" class="ghost chip" @click="applyStatPreset('striker')">Striker</button>
        </div>
      </div>

      <div class="grid two">
        <label>
          HP
          <input type="number" min="1" max="999" v-model.number="local.stats.hp" />
        </label>
        <label>
          Speed
          <input type="number" min="1" max="999" v-model.number="local.stats.speed" />
        </label>
        <label>
          Power
          <input type="number" min="1" max="999" v-model.number="local.stats.power" />
        </label>
      </div>
    </div>

    <div class="card col">
      <div class="row" style="justify-content: space-between; align-items: center;">
        <strong>Modules</strong>
        <button type="button" class="ghost" @click="addBlankModule" :disabled="local.modules.length >= 12">Add Blank</button>
      </div>

      <div class="row module-picker" v-if="moduleOptions.length">
        <select v-model="newModuleType">
          <option value="weapon">Weapon</option>
          <option value="defense">Defense</option>
          <option value="mobility">Mobility</option>
          <option value="utility">Utility</option>
        </select>
        <select v-model="newModuleSlug">
          <option value="">Select module</option>
          <option v-for="option in filteredModuleOptions" :key="option.slug" :value="option.slug">
            {{ option.name }}
          </option>
        </select>
        <button type="button" class="ghost" @click="addModuleFromPicker" :disabled="!newModuleSlug || local.modules.length >= 12">
          Add Module
        </button>
      </div>

      <div v-if="!local.modules.length" class="muted">No modules yet. Add one from the picker above.</div>

      <div v-for="(module, idx) in local.modules" :key="idx" class="module-row">
        <div class="row" style="justify-content: space-between; align-items: center;">
          <strong>Slot {{ idx + 1 }}</strong>
          <button class="ghost" type="button" @click="removeModule(idx)">Remove</button>
        </div>

        <div class="row">
          <select v-model="module.type">
            <option value="weapon">Weapon</option>
            <option value="defense">Defense</option>
            <option value="mobility">Mobility</option>
            <option value="utility">Utility</option>
          </select>
          <select :value="module.slug" @change="setModuleSlug(module, $event)">
            <option value="">Custom slug...</option>
            <option
              v-for="option in moduleOptionsByType(module.type)"
              :key="`${module.type}-${option.slug}`"
              :value="option.slug"
            >
              {{ option.name }}
            </option>
          </select>
        </div>
        <input v-model="module.slug" placeholder="module-laser-mk1" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';

type ModuleOption = { slug: string; name: string; type: string };
type ChassisOption = { slug: string; name: string };

const props = withDefaults(
  defineProps<{
    modelValue: any;
    moduleOptions?: ModuleOption[];
    chassisOptions?: ChassisOption[];
  }>(),
  {
    moduleOptions: () => [],
    chassisOptions: () => [],
  }
);

const emit = defineEmits<{ (e: 'update:modelValue', value: any): void }>();

const local = reactive<any>({
  name: '',
  chassis: 'chassis-starter',
  lane_pref: 'adaptive',
  modules: [],
  stats: {
    hp: 100,
    speed: 10,
    power: 10,
  },
});

const syncingFromParent = ref(false);
const newModuleType = ref<'weapon' | 'defense' | 'mobility' | 'utility'>('weapon');
const newModuleSlug = ref('');

const moduleOptions = computed(() => props.moduleOptions || []);
const chassisOptions = computed(() => props.chassisOptions || []);
const filteredModuleOptions = computed(() => moduleOptions.value.filter((option) => option.type === newModuleType.value));

watch(
  filteredModuleOptions,
  (options) => {
    if (!options.length) {
      newModuleSlug.value = '';
      return;
    }
    if (!options.some((option) => option.slug === newModuleSlug.value)) {
      newModuleSlug.value = options[0].slug;
    }
  },
  { immediate: true }
);

watch(
  () => props.modelValue,
  async (value) => {
    syncingFromParent.value = true;
    applyFromModel(value);
    await nextTick();
    syncingFromParent.value = false;
  },
  { deep: true, immediate: true }
);

watch(
  () => local,
  () => {
    if (syncingFromParent.value) {
      return;
    }
    emit('update:modelValue', JSON.parse(JSON.stringify(local)));
  },
  { deep: true }
);

function applyFromModel(value: any) {
  const normalized = normalizeModel(value);
  local.name = normalized.name;
  local.chassis = normalized.chassis;
  local.lane_pref = normalized.lane_pref;
  local.stats.hp = normalized.stats.hp;
  local.stats.speed = normalized.stats.speed;
  local.stats.power = normalized.stats.power;
  local.modules.splice(0, local.modules.length, ...normalized.modules);
}

function normalizeModel(value: any) {
  const modules = Array.isArray(value?.modules)
    ? value.modules.map((module: any) => ({
        type: String(module?.type || 'weapon'),
        slug: String(module?.slug || ''),
      }))
    : [];

  return {
    name: String(value?.name || ''),
    chassis: String(value?.chassis || 'chassis-starter'),
    lane_pref: String(value?.lane_pref || 'adaptive'),
    modules,
    stats: {
      hp: Number(value?.stats?.hp || 100),
      speed: Number(value?.stats?.speed || 10),
      power: Number(value?.stats?.power || 10),
    },
  };
}

function addBlankModule() {
  if (local.modules.length >= 12) {
    return;
  }
  local.modules.push({ type: 'weapon', slug: '' });
}

function addModuleFromPicker() {
  if (!newModuleSlug.value || local.modules.length >= 12) {
    return;
  }
  local.modules.push({ type: newModuleType.value, slug: newModuleSlug.value });
}

function removeModule(idx: number) {
  local.modules.splice(idx, 1);
}

function setModuleSlug(module: any, event: Event) {
  const target = event.target as HTMLSelectElement | null;
  module.slug = target?.value || '';
}

function moduleOptionsByType(type: string) {
  return moduleOptions.value.filter((option) => option.type === type);
}

function applyStatPreset(preset: 'balanced' | 'tank' | 'striker') {
  if (preset === 'tank') {
    local.stats.hp = 180;
    local.stats.speed = 8;
    local.stats.power = 12;
    return;
  }
  if (preset === 'striker') {
    local.stats.hp = 95;
    local.stats.speed = 18;
    local.stats.power = 20;
    return;
  }
  local.stats.hp = 130;
  local.stats.speed = 12;
  local.stats.power = 14;
}
</script>

<style scoped>
.module-row {
  border: 1px solid rgba(142, 166, 203, 0.24);
  background: rgba(8, 21, 42, 0.5);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.module-picker {
  align-items: center;
}

.chip-row {
  gap: 8px;
}

.chip {
  padding: 6px 10px;
  font-size: 12px;
}

.chip.active {
  border-color: rgba(74, 222, 128, 0.6);
  background: rgba(22, 163, 74, 0.18);
}
</style>
