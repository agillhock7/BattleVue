<template>
  <section class="workshop-layout">
    <aside class="panel col workshop-sidebar">
      <h2 style="margin: 0;">Bot Forge</h2>
      <p class="muted" style="margin: 0;">Pick a style, tune a few settings, and save. Advanced editing is optional.</p>

      <div class="card col starter-card">
        <strong>Start Here</strong>
        <div class="step-row"><span>1</span>Pick bot style</div>
        <div class="row">
          <button class="ghost" @click="applyBlueprintPreset('balanced')">Balanced</button>
          <button class="ghost" @click="applyBlueprintPreset('tank')">Tank</button>
          <button class="ghost" @click="applyBlueprintPreset('striker')">Striker</button>
        </div>

        <div class="step-row"><span>2</span>Pick AI style</div>
        <div class="row">
          <button class="ghost" @click="applyRulesetPreset('balanced')">Balanced AI</button>
          <button class="ghost" @click="applyRulesetPreset('aggressive')">Aggressive AI</button>
          <button class="ghost" @click="applyRulesetPreset('defensive')">Defensive AI</button>
        </div>

        <div class="step-row"><span>3</span>Save both</div>
        <button @click="buildStarterBot" :disabled="savingAll">
          {{ savingAll ? 'Building...' : 'Create Starter Bot' }}
        </button>
      </div>

      <div class="card col">
        <strong>Unlocked Parts</strong>
        <div class="inventory-stats">
          <span>Chassis: {{ chassisItems.length }}</span>
          <span>Modules: {{ moduleItems.length }}</span>
          <span>Total: {{ inventory.length }}</span>
        </div>
        <details class="inventory-details" v-if="inventory.length">
          <summary>View parts list</summary>
          <div class="list" style="margin-top: 8px;">
            <div class="inventory-row" v-for="item in inventory" :key="item.slug">
              <strong>{{ item.name }}</strong>
              <span class="muted">{{ item.item_type }} | Qty {{ item.quantity }}</span>
            </div>
          </div>
        </details>
      </div>

      <div class="card col">
        <div class="row" style="justify-content: space-between; align-items: center;">
          <strong>Saved Blueprints</strong>
          <span class="muted">{{ blueprints.length }}</span>
        </div>
        <div class="list" v-if="blueprints.length">
          <button class="ghost saved-btn" v-for="bp in blueprints" :key="bp.id" @click="loadBlueprint(bp)">
            <strong>{{ bp.name }}</strong>
            <span class="muted">{{ bp.chassis }}</span>
          </button>
        </div>
        <div class="muted" v-else>No saved blueprints yet.</div>
      </div>

      <div class="card col">
        <div class="row" style="justify-content: space-between; align-items: center;">
          <strong>Saved AI Profiles</strong>
          <span class="muted">{{ rulesets.length }}</span>
        </div>
        <div class="list" v-if="rulesets.length">
          <button class="ghost saved-btn" v-for="rs in rulesets" :key="rs.id" @click="loadRuleset(rs)">
            <strong>{{ rs.name }}</strong>
            <span class="muted">{{ (rs.rules || []).length }} rules</span>
          </button>
        </div>
        <div class="muted" v-else>No saved AI profiles yet.</div>
      </div>
    </aside>

    <article class="col workshop-main">
      <BotBuildPreview
        :blueprint="blueprintDraft"
        :ruleset="rulesetDraft"
        :blueprint-validation="blueprintValidation"
        :ruleset-validation="rulesetValidation"
      />

      <div class="panel col workshop-control">
        <div class="row" style="justify-content: space-between; align-items: center;">
          <h3 style="margin: 0;">Edit Bot</h3>
          <div class="row">
            <button class="ghost" :class="{ active: editorMode === 'simple' }" @click="editorMode = 'simple'">Simple</button>
            <button class="ghost" :class="{ active: editorMode === 'advanced' }" @click="editorMode = 'advanced'">Advanced</button>
            <button class="ghost" @click="resetDrafts">Reset</button>
          </div>
        </div>

        <template v-if="editorMode === 'simple'">
          <div class="grid two">
            <label>
              Bot Name
              <input v-model="blueprintDraft.name" placeholder="My Battle Bot" />
            </label>
            <label>
              Lane Strategy
              <select v-model="blueprintDraft.lane_pref">
                <option value="left">Left</option>
                <option value="mid">Mid</option>
                <option value="right">Right</option>
                <option value="adaptive">Adaptive</option>
              </select>
            </label>
          </div>

          <div class="card col">
            <strong>Core Stats</strong>
            <div class="grid three">
              <label>
                HP
                <input type="range" min="60" max="220" step="5" v-model.number="blueprintDraft.stats.hp" />
                <span class="muted">{{ blueprintDraft.stats.hp }}</span>
              </label>
              <label>
                Speed
                <input type="range" min="5" max="220" step="5" v-model.number="blueprintDraft.stats.speed" />
                <span class="muted">{{ blueprintDraft.stats.speed }}</span>
              </label>
              <label>
                Power
                <input type="range" min="5" max="220" step="5" v-model.number="blueprintDraft.stats.power" />
                <span class="muted">{{ blueprintDraft.stats.power }}</span>
              </label>
            </div>
          </div>

          <div class="card col">
            <strong>Modules (tap to add/remove)</strong>
            <div class="row">
              <button
                type="button"
                class="ghost chip"
                v-for="module in moduleLibrary"
                :key="`pick-${module.slug}`"
                :class="{ selected: hasModule(module.slug) }"
                @click="toggleModule(module)"
              >
                {{ module.name }}
              </button>
            </div>
            <div class="row" v-if="(blueprintDraft.modules || []).length">
              <span class="module-tag" v-for="(module, idx) in blueprintDraft.modules" :key="`${module.slug}-${idx}`">
                {{ module.slug }}
                <button type="button" @click="removeModuleAt(idx)">x</button>
              </span>
            </div>
          </div>

          <div class="card col">
            <strong>AI Behavior</strong>
            <div class="row">
              <button class="ghost" @click="applyRulesetPreset('balanced')">Balanced</button>
              <button class="ghost" @click="applyRulesetPreset('aggressive')">Aggressive</button>
              <button class="ghost" @click="applyRulesetPreset('defensive')">Defensive</button>
            </div>
          </div>
        </template>

        <template v-else>
          <BlueprintBuilder
            v-model="blueprintDraft"
            :module-options="moduleLibrary"
            :chassis-options="chassisLibrary"
          />
          <RuleBuilder v-model="rulesetDraft" />
        </template>
      </div>

      <div class="row action-row">
        <button @click="saveBlueprint">{{ editingBlueprintId ? 'Update Blueprint' : 'Save Blueprint' }}</button>
        <button @click="saveRuleset">{{ editingRulesetId ? 'Update AI Profile' : 'Save AI Profile' }}</button>
        <button class="ghost" @click="validateAll">Validate Both</button>
      </div>

      <div class="card validation" v-if="validationSummary.length">
        <strong>Validation Issues</strong>
        <ul>
          <li v-for="issue in validationSummary" :key="issue">{{ issue }}</li>
        </ul>
      </div>

      <p class="status-line" :class="{ error: statusIsError }">{{ status }}</p>
    </article>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import BotBuildPreview from '@/components/BotBuildPreview.vue';
import BlueprintBuilder from '@/components/BlueprintBuilder.vue';
import RuleBuilder from '@/components/RuleBuilder.vue';
import { api } from '@/services/api';
import { loadDraft, saveDraft } from '@/services/idb';

type ValidationResponse = { valid: boolean; errors: string[] };
type ModuleOption = { slug: string; name: string; type: string };
type ChassisOption = { slug: string; name: string };

const inventory = ref<any[]>([]);
const blueprints = ref<any[]>([]);
const rulesets = ref<any[]>([]);

const blueprintDraft = ref<any>({
  name: '',
  chassis: 'chassis-starter',
  lane_pref: 'adaptive',
  modules: [],
  stats: { hp: 130, speed: 12, power: 14 },
});
const rulesetDraft = ref<any>({ name: '', rules: [] });

const blueprintValidation = ref<ValidationResponse | null>(null);
const rulesetValidation = ref<ValidationResponse | null>(null);

const editorMode = ref<'simple' | 'advanced'>('simple');
const savingAll = ref(false);

const status = ref('Pick a style and create your bot.');
const statusIsError = ref(false);

let editingBlueprintId = 0;
let editingRulesetId = 0;

const chassisItems = computed(() => inventory.value.filter((item) => item.item_type === 'chassis'));
const moduleItems = computed(() => inventory.value.filter((item) => item.item_type === 'module'));

const moduleLibrary = computed<ModuleOption[]>(() => {
  const fromInventory = moduleItems.value.map((item) => ({
    slug: String(item.slug),
    name: String(item.name),
    type: inferModuleType(item.slug),
  }));

  const fallback: ModuleOption[] = [
    { slug: 'module-laser-mk1', name: 'Laser MK1', type: 'weapon' },
    { slug: 'module-shield-mk1', name: 'Shield MK1', type: 'defense' },
    { slug: 'module-thruster-mk1', name: 'Thruster MK1', type: 'mobility' },
    { slug: 'module-scanner-mk1', name: 'Scanner MK1', type: 'utility' },
  ];

  const seen = new Set<string>();
  return [...fromInventory, ...fallback].filter((item) => {
    if (seen.has(item.slug)) {
      return false;
    }
    seen.add(item.slug);
    return true;
  });
});

const chassisLibrary = computed<ChassisOption[]>(() => {
  const fromInventory = chassisItems.value.map((item) => ({
    slug: String(item.slug),
    name: String(item.name),
  }));

  const fallback: ChassisOption[] = [{ slug: 'chassis-starter', name: 'Starter Chassis' }];

  const seen = new Set<string>();
  return [...fromInventory, ...fallback].filter((item) => {
    if (seen.has(item.slug)) {
      return false;
    }
    seen.add(item.slug);
    return true;
  });
});

const validationSummary = computed(() => {
  const out: string[] = [];
  if (blueprintValidation.value && !blueprintValidation.value.valid) {
    out.push(...blueprintValidation.value.errors.map((e) => `Blueprint: ${e}`));
  }
  if (rulesetValidation.value && !rulesetValidation.value.valid) {
    out.push(...rulesetValidation.value.errors.map((e) => `AI Profile: ${e}`));
  }
  return out;
});

onMounted(async () => {
  await Promise.all([loadInventory(), loadBlueprints(), loadRulesets(), hydrateDrafts()]);
  if (!blueprintDraft.value.name) {
    applyBlueprintPreset('balanced');
  }
  if (!rulesetDraft.value.name) {
    applyRulesetPreset('balanced');
  }
});

watch(
  () => blueprintDraft.value,
  (value) => saveDraft('blueprint', value),
  { deep: true }
);

watch(
  () => rulesetDraft.value,
  (value) => saveDraft('ruleset', value),
  { deep: true }
);

async function hydrateDrafts() {
  const bp = await loadDraft('blueprint');
  const rs = await loadDraft('ruleset');
  if (bp?.value) {
    blueprintDraft.value = bp.value;
  }
  if (rs?.value) {
    rulesetDraft.value = rs.value;
  }
}

async function loadInventory() {
  const data = await api.get<{ inventory: any[] }>('/inventory');
  inventory.value = data.inventory;
}

async function loadBlueprints() {
  const data = await api.get<{ blueprints: any[] }>('/blueprints/list');
  blueprints.value = data.blueprints;
}

async function loadRulesets() {
  const data = await api.get<{ rulesets: any[] }>('/rulesets/list');
  rulesets.value = data.rulesets;
}

function loadBlueprint(blueprint: any) {
  editingBlueprintId = Number(blueprint.id);
  blueprintDraft.value = {
    name: blueprint.name,
    chassis: blueprint.chassis,
    lane_pref: blueprint.lane_pref,
    modules: blueprint.modules || [],
    stats: blueprint.stats || { hp: 130, speed: 12, power: 14 },
  };
  setStatus(`Loaded blueprint "${blueprint.name}".`, false);
}

function loadRuleset(ruleset: any) {
  editingRulesetId = Number(ruleset.id);
  rulesetDraft.value = {
    name: ruleset.name,
    rules: ruleset.rules || [],
  };
  setStatus(`Loaded AI profile "${ruleset.name}".`, false);
}

function resetDrafts() {
  editingBlueprintId = 0;
  editingRulesetId = 0;
  blueprintDraft.value = {
    name: '',
    chassis: 'chassis-starter',
    lane_pref: 'adaptive',
    modules: [],
    stats: { hp: 130, speed: 12, power: 14 },
  };
  rulesetDraft.value = { name: '', rules: [] };
  blueprintValidation.value = null;
  rulesetValidation.value = null;
  setStatus('Drafts reset.', false);
}

async function buildStarterBot() {
  savingAll.value = true;
  try {
    editingBlueprintId = 0;
    editingRulesetId = 0;
    applyBlueprintPreset('balanced');
    applyRulesetPreset('balanced');
    await saveBlueprint();
    await saveRuleset();
    setStatus('Starter bot created and saved.', false);
  } finally {
    savingAll.value = false;
  }
}

async function saveBlueprint() {
  const validation = await runBlueprintValidation();
  if (!validation.valid) {
    setStatus('Fix blueprint issues before saving.', true);
    return;
  }

  if (editingBlueprintId) {
    await api.post('/blueprints/update', { ...blueprintDraft.value, blueprint_id: editingBlueprintId });
    setStatus('Blueprint updated.', false);
  } else {
    const created = await api.post<{ blueprint_id: number }>('/blueprints/create', blueprintDraft.value);
    editingBlueprintId = Number(created.blueprint_id || 0);
    setStatus('Blueprint saved.', false);
  }

  await loadBlueprints();
}

async function saveRuleset() {
  const validation = await runRulesetValidation();
  if (!validation.valid) {
    setStatus('Fix AI profile issues before saving.', true);
    return;
  }

  if (editingRulesetId) {
    await api.post('/rulesets/update', { ...rulesetDraft.value, ruleset_id: editingRulesetId });
    setStatus('AI profile updated.', false);
  } else {
    const created = await api.post<{ ruleset_id: number }>('/rulesets/create', rulesetDraft.value);
    editingRulesetId = Number(created.ruleset_id || 0);
    setStatus('AI profile saved.', false);
  }

  await loadRulesets();
}

async function validateAll() {
  const [bp, rs] = await Promise.all([runBlueprintValidation(), runRulesetValidation()]);
  setStatus(bp.valid && rs.valid ? 'Everything is ready for battle.' : 'Validation issues found.', !(bp.valid && rs.valid));
}

async function runBlueprintValidation() {
  const data = await api.post<ValidationResponse>('/validate/blueprint', blueprintDraft.value);
  blueprintValidation.value = data;
  return data;
}

async function runRulesetValidation() {
  const data = await api.post<ValidationResponse>('/validate/ruleset', rulesetDraft.value);
  rulesetValidation.value = data;
  return data;
}

function applyBlueprintPreset(type: 'balanced' | 'tank' | 'striker') {
  const modules = buildModulePreset(type);
  if (type === 'tank') {
    blueprintDraft.value = {
      name: 'Bulwark Tank',
      chassis: pickChassisSlug(),
      lane_pref: 'mid',
      modules,
      stats: { hp: 180, speed: 8, power: 12 },
    };
    setStatus('Applied Tank preset.', false);
    return;
  }

  if (type === 'striker') {
    blueprintDraft.value = {
      name: 'Striker Blitz',
      chassis: pickChassisSlug(),
      lane_pref: 'adaptive',
      modules,
      stats: { hp: 95, speed: 18, power: 20 },
    };
    setStatus('Applied Striker preset.', false);
    return;
  }

  blueprintDraft.value = {
    name: 'Balanced Vanguard',
    chassis: pickChassisSlug(),
    lane_pref: 'adaptive',
    modules,
    stats: { hp: 130, speed: 12, power: 14 },
  };
  setStatus('Applied Balanced preset.', false);
}

function applyRulesetPreset(type: 'balanced' | 'aggressive' | 'defensive') {
  if (type === 'aggressive') {
    rulesetDraft.value = {
      name: 'Aggressive Sweep',
      rules: [
        { priority: 100, when: { sensor: 'cooldown_ready', op: '==', value: true }, then: { action: 'attack_lane', params: { lane: 'mid' } } },
        { priority: 90, when: { sensor: 'enemy_hp_pct', op: '<=', value: 30 }, then: { action: 'attack_lane', params: { lane: 'mid' } } },
        { priority: 20, when: { sensor: 'tick', op: '>=', value: 1 }, then: { action: 'wait', params: { lane: 'mid' } } },
      ],
    };
    setStatus('Applied Aggressive AI preset.', false);
    return;
  }

  if (type === 'defensive') {
    rulesetDraft.value = {
      name: 'Defensive Anchor',
      rules: [
        { priority: 100, when: { sensor: 'self_hp_pct', op: '<=', value: 40 }, then: { action: 'guard', params: { lane: 'mid' } } },
        { priority: 80, when: { sensor: 'enemy_lane', op: '==', value: 'left' }, then: { action: 'shift_lane', params: { lane: 'left' } } },
        { priority: 50, when: { sensor: 'tick', op: '>=', value: 1 }, then: { action: 'attack_lane', params: { lane: 'mid' } } },
      ],
    };
    setStatus('Applied Defensive AI preset.', false);
    return;
  }

  rulesetDraft.value = {
    name: 'Balanced Core',
    rules: [
      { priority: 100, when: { sensor: 'self_hp_pct', op: '<=', value: 35 }, then: { action: 'guard', params: { lane: 'mid' } } },
      { priority: 90, when: { sensor: 'cooldown_ready', op: '==', value: true }, then: { action: 'attack_lane', params: { lane: 'mid' } } },
      { priority: 40, when: { sensor: 'tick', op: '>=', value: 1 }, then: { action: 'wait', params: { lane: 'mid' } } },
    ],
  };
  setStatus('Applied Balanced AI preset.', false);
}

function buildModulePreset(type: 'balanced' | 'tank' | 'striker') {
  if (type === 'tank') {
    return [
      { type: 'defense', slug: pickModuleSlug('defense', 'module-shield-mk1') },
      { type: 'weapon', slug: pickModuleSlug('weapon', 'module-laser-mk1') },
    ];
  }
  if (type === 'striker') {
    return [
      { type: 'mobility', slug: pickModuleSlug('mobility', 'module-thruster-mk1') },
      { type: 'weapon', slug: pickModuleSlug('weapon', 'module-laser-mk1') },
    ];
  }
  return [
    { type: 'weapon', slug: pickModuleSlug('weapon', 'module-laser-mk1') },
    { type: 'defense', slug: pickModuleSlug('defense', 'module-shield-mk1') },
    { type: 'utility', slug: pickModuleSlug('utility', 'module-scanner-mk1') },
  ];
}

function pickChassisSlug() {
  return chassisLibrary.value[0]?.slug || 'chassis-starter';
}

function pickModuleSlug(type: string, fallback: string) {
  return moduleLibrary.value.find((module) => module.type === type)?.slug || fallback;
}

function hasModule(slug: string) {
  return Array.isArray(blueprintDraft.value.modules) && blueprintDraft.value.modules.some((module: any) => module.slug === slug);
}

function toggleModule(module: ModuleOption) {
  if (!Array.isArray(blueprintDraft.value.modules)) {
    blueprintDraft.value.modules = [];
  }

  const idx = blueprintDraft.value.modules.findIndex((entry: any) => entry.slug === module.slug);
  if (idx >= 0) {
    blueprintDraft.value.modules.splice(idx, 1);
    return;
  }

  if (blueprintDraft.value.modules.length >= 12) {
    setStatus('Max 12 modules allowed.', true);
    return;
  }

  blueprintDraft.value.modules.push({ type: module.type, slug: module.slug });
}

function removeModuleAt(index: number) {
  if (!Array.isArray(blueprintDraft.value.modules)) {
    return;
  }
  blueprintDraft.value.modules.splice(index, 1);
}

function inferModuleType(slug: string) {
  if (slug.includes('shield') || slug.includes('armor') || slug.includes('guard')) {
    return 'defense';
  }
  if (slug.includes('thruster') || slug.includes('boost') || slug.includes('dash')) {
    return 'mobility';
  }
  if (slug.includes('scan') || slug.includes('radar') || slug.includes('support')) {
    return 'utility';
  }
  return 'weapon';
}

function setStatus(message: string, isError: boolean) {
  status.value = message;
  statusIsError.value = isError;
}
</script>

<style scoped>
.workshop-layout {
  display: grid;
  grid-template-columns: 330px minmax(0, 1fr);
  gap: 14px;
}

.workshop-sidebar {
  max-height: calc(100vh - 100px);
  position: sticky;
  top: 84px;
  overflow: auto;
}

.workshop-main {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.workshop-control {
  background:
    radial-gradient(circle at 12% 15%, rgba(56, 189, 248, 0.12), transparent 40%),
    rgba(16, 29, 52, 0.92);
}

.starter-card {
  gap: 10px;
}

.step-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.step-row span {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(34, 211, 238, 0.2);
  border: 1px solid rgba(34, 211, 238, 0.35);
  font-size: 11px;
}

.inventory-stats {
  display: flex;
  gap: 10px;
  color: #9fb1cc;
  font-size: 13px;
  flex-wrap: wrap;
}

.inventory-details summary {
  cursor: pointer;
  color: #cfe3ff;
}

.inventory-row {
  border: 1px solid rgba(142, 166, 203, 0.2);
  border-radius: 10px;
  padding: 8px;
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.saved-btn {
  text-align: left;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.action-row {
  align-items: center;
}

.validation ul {
  margin: 8px 0 0 18px;
  padding: 0;
}

.status-line {
  margin: 0;
  color: #9fb1cc;
}

.status-line.error {
  color: #fca5a5;
}

button.active {
  border-color: rgba(74, 222, 128, 0.7);
  background: rgba(22, 163, 74, 0.2);
}

.grid.three {
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.chip {
  padding: 6px 10px;
  font-size: 12px;
}

.chip.selected {
  border-color: rgba(74, 222, 128, 0.65);
  background: rgba(22, 163, 74, 0.2);
}

.module-tag {
  border: 1px solid rgba(142, 166, 203, 0.35);
  border-radius: 999px;
  padding: 4px 8px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.module-tag button {
  border: 0;
  background: transparent;
  color: #d9e8ff;
  padding: 0;
  cursor: pointer;
}

@media (max-width: 1080px) {
  .workshop-layout {
    grid-template-columns: 1fr;
  }

  .workshop-sidebar {
    position: static;
    max-height: none;
  }
}
</style>
