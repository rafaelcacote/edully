<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Save, Search, X, Users } from 'lucide-vue-next';
import { ref, watch, onMounted, computed } from 'vue';

interface ClassData {
    id?: string;
    nome?: string;
    serie?: string | null;
    turma_letra?: string | null;
    capacidade?: number | null;
    ano_letivo?: number | null;
    professor_id?: string | null;
    professor_ids?: string[];
    ativo?: boolean;
}

interface Teacher {
    id: string;
    nome_completo: string;
}

const props = defineProps<{
    classData?: ClassData;
    teachers?: Teacher[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const nome = ref(props.classData?.nome || '');
const serie = ref(props.classData?.serie || '');
const turmaLetra = ref(props.classData?.turma_letra || '');
const anoLetivo = ref(props.classData?.ano_letivo || new Date().getFullYear());
const professorId = ref(props.classData?.professor_id || '');
const professorIds = ref<string[]>(
    Array.isArray(props.classData?.professor_ids)
        ? props.classData.professor_ids.map(id => String(id))
        : []
);
const capacidade = ref(props.classData?.capacidade || '');
const ativo = ref(props.classData?.ativo !== false);
const searchQuery = ref('');

const filteredTeachers = computed(() => {
    if (!props.teachers) {
        return [];
    }
    if (!searchQuery.value.trim()) {
        return props.teachers;
    }
    const query = searchQuery.value.toLowerCase().trim();
    return props.teachers.filter(teacher =>
        teacher.nome_completo.toLowerCase().includes(query)
    );
});

const selectedTeachers = computed(() => {
    if (!props.teachers) {
        return [];
    }
    return props.teachers.filter(teacher => professorIds.value.includes(String(teacher.id)));
});

const toggleTeacher = (teacherId: string) => {
    const teacherIdStr = String(teacherId);
    const index = professorIds.value.indexOf(teacherIdStr);
    if (index > -1) {
        professorIds.value.splice(index, 1);
    } else {
        professorIds.value.push(teacherIdStr);
    }
};

const removeTeacher = (teacherId: string) => {
    const teacherIdStr = String(teacherId);
    const index = professorIds.value.indexOf(teacherIdStr);
    if (index > -1) {
        professorIds.value.splice(index, 1);
    }
};

const clearSearch = () => {
    searchQuery.value = '';
};

watch(() => props.classData, (newData) => {
    if (newData) {
        nome.value = newData.nome || '';
        serie.value = newData.serie || '';
        turmaLetra.value = newData.turma_letra || '';
        anoLetivo.value = newData.ano_letivo || new Date().getFullYear();
        professorId.value = newData.professor_id || '';
        professorIds.value = Array.isArray(newData.professor_ids)
            ? newData.professor_ids.map(id => String(id))
            : [];
        capacidade.value = newData.capacidade || '';
        ativo.value = newData.ativo !== false;
    }
}, { immediate: true, deep: true });

onMounted(() => {
    // Garantir que professorIds seja sempre um array de strings
    if (!Array.isArray(professorIds.value)) {
        professorIds.value = [];
    } else {
        professorIds.value = professorIds.value.map(id => String(id));
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="nome">Nome da turma</Label>
                <input
                    id="nome"
                    name="nome"
                    v-model="nome"
                    type="text"
                    placeholder="Ex: 5º Ano A"
                    required
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.nome" />
            </div>

            <div class="grid gap-2">
                <Label for="serie">Série</Label>
                <input
                    id="serie"
                    name="serie"
                    v-model="serie"
                    type="text"
                    placeholder="Ex: 5º ano"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.serie" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="turma_letra">Turma (Letra)</Label>
                <select
                    id="turma_letra"
                    name="turma_letra"
                    v-model="turmaLetra"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione a turma</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                    <option value="E">E</option>
                    <option value="F">F</option>
                </select>
                <InputError :message="errors.turma_letra" />
            </div>

            <div class="grid gap-2">
                <Label for="ano_letivo">Ano letivo</Label>
                <input
                    id="ano_letivo"
                    name="ano_letivo"
                    v-model="anoLetivo"
                    type="number"
                    placeholder="Ex: 2024"
                    min="2000"
                    max="2100"
                    required
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.ano_letivo" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-3">
                <div class="flex items-center justify-between">
                    <Label for="professor_ids" class="flex items-center gap-2">
                        <Users class="h-4 w-4" />
                        Professores Responsáveis
                    </Label>
                    <span v-if="professorIds.length > 0" class="text-xs text-muted-foreground">
                        {{ professorIds.length }} {{ professorIds.length === 1 ? 'selecionado' : 'selecionados' }}
                    </span>
                </div>

                <!-- Professores Selecionados (Badges) -->
                <div v-if="selectedTeachers.length > 0" class="flex flex-wrap gap-2 rounded-lg border border-input bg-muted/30 p-3 min-h-[60px]">
                    <Badge
                        v-for="teacher in selectedTeachers"
                        :key="teacher.id"
                        variant="secondary"
                        class="group flex items-center gap-1.5 pr-1.5"
                    >
                        <span>{{ teacher.nome_completo }}</span>
                        <button
                            type="button"
                            @click="removeTeacher(teacher.id)"
                            class="ml-1 rounded-full p-0.5 transition-colors hover:bg-destructive/20 hover:text-destructive focus:outline-none focus:ring-2 focus:ring-ring"
                            :aria-label="`Remover ${teacher.nome_completo}`"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </Badge>
                </div>

                <!-- Campo de Busca -->
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar professores..."
                        class="pl-9 pr-9"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="clearSearch"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        aria-label="Limpar busca"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Lista de Professores -->
                <div class="rounded-lg border border-input bg-background">
                    <div
                        v-if="filteredTeachers.length === 0"
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        <p v-if="searchQuery">Nenhum professor encontrado com "{{ searchQuery }}"</p>
                        <p v-else>Nenhum professor disponível</p>
                    </div>
                    <div
                        v-else
                        class="max-h-[240px] overflow-y-auto p-2"
                    >
                        <label
                            v-for="teacher in filteredTeachers"
                            :key="teacher.id"
                            class="flex items-center gap-3 rounded-md p-2.5 transition-colors hover:bg-accent cursor-pointer"
                            @click="toggleTeacher(teacher.id)"
                        >
                            <Checkbox
                                :model-value="professorIds.includes(String(teacher.id))"
                                :aria-label="`Selecionar ${teacher.nome_completo}`"
                                @click.stop
                            />
                            <span class="flex-1 text-sm">{{ teacher.nome_completo }}</span>
                        </label>
                    </div>
                </div>

                <!-- Inputs hidden para o formulário -->
                <template v-for="(teacherId, index) in professorIds" :key="teacherId">
                    <input
                        type="hidden"
                        :name="`professor_ids[${index}]`"
                        :value="teacherId"
                    />
                </template>

                <InputError :message="errors['professor_ids'] || errors['professor_ids.0']" />
            </div>

            <div class="grid gap-2">
                <Label for="capacidade">Capacidade</Label>
                <input
                    id="capacidade"
                    name="capacidade"
                    v-model="capacidade"
                    type="number"
                    placeholder="Ex: 30"
                    min="1"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.capacidade" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="ativo">Status</Label>
                <label
                    class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm cursor-pointer"
                >
                    <input
                        type="hidden"
                        name="ativo"
                        :value="ativo ? '1' : '0'"
                    />
                    <input
                        id="ativo"
                        type="checkbox"
                        v-model="ativo"
                        class="h-4 w-4 rounded border border-input cursor-pointer"
                    />
                    <span class="text-muted-foreground">
                        {{ ativo ? 'Ativa' : 'Inativa' }}
                    </span>
                </label>
                <InputError :message="errors.ativo" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>

