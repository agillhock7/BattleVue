<template>
  <div class="panel col">
    <div class="row" style="justify-content: space-between; align-items: center;">
      <h3 style="margin: 0;">Ruleset Builder</h3>
      <span class="muted">{{ local.rules.length }}/30 rules</span>
    </div>

    <label>
      Ruleset Name
      <input v-model="local.name" placeholder="Aggro Priority" />
    </label>

    <div class="card col">
      <strong>Quick Rule Inserts</strong>
      <div class="row">
        <button class="ghost" type="button" @click="addStarterRule('finish')">Finish Weak Enemy</button>
        <button class="ghost" type="button" @click="addStarterRule('lowhp')">Low HP Guard</button>
        <button class="ghost" type="button" @click="addStarterRule('open')">Open With Mid Attack</button>
      </div>
    </div>

    <div class="list">
      <div v-if="!local.rules.length" class="card muted">No rules yet. Add one starter rule, then tune priority and actions.</div>

      <div v-for="(rule, idx) in local.rules" :key="idx" class="card col">
        <div class="row" style="justify-content: space-between; align-items: center;">
          <strong>Rule {{ idx + 1 }}</strong>
          <button class="ghost" type="button" @click="removeRule(idx)">Remove</button>
        </div>

        <label>
          Priority
          <input type="number" v-model.number="rule.priority" min="0" max="1000" />
        </label>

        <div class="grid two">
          <label>
            Sensor
            <select v-model="rule.when.sensor">
              <option value="self_hp_pct">Self HP %</option>
              <option value="enemy_hp_pct">Enemy HP %</option>
              <option value="self_lane">Self Lane</option>
              <option value="enemy_lane">Enemy Lane</option>
              <option value="tick">Tick</option>
              <option value="cooldown_ready">Cooldown Ready</option>
            </select>
          </label>
          <label>
            Operator
            <select v-model="rule.when.op">
              <option value="==">==</option>
              <option value="!=">!=</option>
              <option value=">">&gt;</option>
              <option value=">=">&gt;=</option>
              <option value="<">&lt;</option>
              <option value="<=">&lt;=</option>
              <option value="in">in</option>
            </select>
          </label>
          <label>
            Value
            <input v-model="rule.when.value" :placeholder="valuePlaceholder(rule.when.sensor)" />
          </label>
        </div>

        <div class="grid two">
          <label>
            Action
            <select v-model="rule.then.action">
              <option value="attack_lane">Attack Lane</option>
              <option value="guard">Guard</option>
              <option value="shift_lane">Shift Lane</option>
              <option value="wait">Wait</option>
            </select>
          </label>

          <label v-if="requiresLane(rule.then.action)">
            Lane Param
            <select v-model="rule.then.params.lane">
              <option value="left">Left</option>
              <option value="mid">Mid</option>
              <option value="right">Right</option>
            </select>
          </label>
        </div>
      </div>
    </div>

    <div class="row">
      <button type="button" class="ghost" @click="addRule">Add Blank Rule</button>
      <button type="button" class="ghost" @click="sortByPriority" :disabled="local.rules.length < 2">Sort By Priority</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, reactive, ref, watch } from 'vue';

const props = defineProps<{ modelValue: any }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: any): void }>();

const local = reactive<any>({
  name: '',
  rules: [],
});

const syncingFromParent = ref(false);

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
    emit('update:modelValue', {
      name: local.name,
      rules: local.rules.map((rule: any) => normalizeRule(rule)),
    });
  },
  { deep: true }
);

function applyFromModel(value: any) {
  const normalized = {
    name: String(value?.name || ''),
    rules: Array.isArray(value?.rules) ? value.rules.map((rule: any) => normalizeRule(rule)) : [],
  };

  local.name = normalized.name;
  local.rules.splice(0, local.rules.length, ...normalized.rules);
}

function normalizeRule(rule: any) {
  return {
    priority: Number(rule?.priority ?? 0),
    when: {
      sensor: String(rule?.when?.sensor ?? 'tick'),
      op: String(rule?.when?.op ?? '>='),
      value: normalizeValue(rule?.when?.value),
    },
    then: {
      action: String(rule?.then?.action ?? 'wait'),
      params: {
        lane: String(rule?.then?.params?.lane ?? 'mid'),
      },
    },
  };
}

function normalizeValue(value: any) {
  if (typeof value === 'string') {
    if (/^\d+$/.test(value)) {
      return Number(value);
    }
    if (value === 'true') {
      return true;
    }
    if (value === 'false') {
      return false;
    }
  }
  return value;
}

function addRule() {
  if (local.rules.length >= 30) {
    return;
  }
  local.rules.push({
    priority: local.rules.length,
    when: { sensor: 'tick', op: '>=', value: 1 },
    then: { action: 'attack_lane', params: { lane: 'mid' } },
  });
}

function addStarterRule(type: 'finish' | 'lowhp' | 'open') {
  if (local.rules.length >= 30) {
    return;
  }

  if (type === 'finish') {
    local.rules.push({
      priority: 90,
      when: { sensor: 'enemy_hp_pct', op: '<=', value: 25 },
      then: { action: 'attack_lane', params: { lane: 'mid' } },
    });
    return;
  }

  if (type === 'lowhp') {
    local.rules.push({
      priority: 100,
      when: { sensor: 'self_hp_pct', op: '<=', value: 35 },
      then: { action: 'guard', params: { lane: 'mid' } },
    });
    return;
  }

  local.rules.push({
    priority: 70,
    when: { sensor: 'tick', op: '>=', value: 1 },
    then: { action: 'attack_lane', params: { lane: 'mid' } },
  });
}

function removeRule(index: number) {
  local.rules.splice(index, 1);
}

function sortByPriority() {
  local.rules.sort((a: any, b: any) => Number(b.priority) - Number(a.priority));
}

function requiresLane(action: string) {
  return action === 'attack_lane' || action === 'shift_lane';
}

function valuePlaceholder(sensor: string) {
  if (sensor === 'self_lane' || sensor === 'enemy_lane') {
    return 'left / mid / right';
  }
  if (sensor === 'cooldown_ready') {
    return 'true or false';
  }
  if (sensor === 'tick') {
    return 'e.g. 1';
  }
  return 'e.g. 50';
}
</script>
