<?php

namespace App\Services\Portal;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceKnowledgeChunk;
use App\Models\WorkspaceKnowledgeSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkspaceKnowledge
{
    public function sources(PartnershipWorkspace $workspace): mixed
    {
        return WorkspaceKnowledgeSource::query()->where('workspace_id', $workspace->id);
    }

    public function signature(PartnershipWorkspace $workspace): string
    {
        return hash('sha256', json_encode([
            $workspace->name, $workspace->description,
            $this->sources($workspace)->where('processing_status', 'ready')->orderBy('id')
                ->get(['id', 'revision', 'title', 'business_status', 'embedding_key'])->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    public function chunks(string $text): array
    {
        $size = (int) config('pbr_knowledge.chunk_chars', 1800);
        $overlap = (int) config('pbr_knowledge.chunk_overlap', 200);
        $chunks = [];
        for ($offset = 0, $length = mb_strlen($text); $offset < $length; $offset += $size - $overlap) {
            $part = trim(mb_substr($text, $offset, $size));
            if ($part !== '') $chunks[] = $part;
            if ($offset + $size >= $length) break;
        }

        return $chunks;
    }

    public function approve(WorkspaceKnowledgeSource $source, PartnerAiGateway $gateway): void
    {
        $text = trim((string) $source->reviewed_text);
        if ($text === '') throw new RuntimeException('KNOWLEDGE_TEXT_REQUIRED');
        $parts = $this->chunks($text);
        $embeddings = $gateway->embedPrivate(array_map(fn ($part) => $source->title."\n".$part, $parts));
        DB::transaction(function () use ($source, $parts, $embeddings): void {
            WorkspaceKnowledgeChunk::where('source_id', $source->id)->delete();
            foreach ($parts as $index => $part) {
                WorkspaceKnowledgeChunk::create([
                    'workspace_id' => $source->workspace_id, 'source_id' => $source->id,
                    'revision' => $source->revision, 'content' => $part,
                    'embedding' => $embeddings['vectors'][$index], 'embedding_key' => $embeddings['key'],
                ]);
            }
            $source->update(['processing_status' => 'ready', 'ready_at' => now(), 'embedding_key' => $embeddings['key']]);
        });
    }

    public function retrieve(PartnershipWorkspace $workspace, string $question, PartnerAiGateway $gateway): array
    {
        $sources = $this->sources($workspace)->where('processing_status', 'ready')->orderBy('id')->get()->keyBy('id');
        if ($sources->isEmpty()) return ['sources' => [], 'excerpts' => []];
        $embedding = $gateway->embedPrivate([$question], 'query');
        // A changed model/dimension requires explicit re-approval, never comparison
        // between incompatible vectors or silently missing private business facts.
        foreach ($sources as $source) {
            if ($source->embedding_key !== $embedding['key']) throw new RuntimeException('KNOWLEDGE_REINDEX_REQUIRED');
        }
        // Stable source labels across follow-up answers. Any approved-set change
        // invalidates the conversation signature before its history is reused.
        $labels = array_flip($sources->keys()->all());
        $best = [];
        foreach (WorkspaceKnowledgeChunk::where('workspace_id', $workspace->id)->whereIn('source_id', $sources->keys())->cursor() as $chunk) {
            $source = $sources->get($chunk->source_id);
            if ($chunk->revision !== $source->revision || $chunk->embedding_key !== $embedding['key']) continue;
            $score = $this->cosine($chunk->embedding, $embedding['vectors'][0]);
            $best[] = ['chunk' => $chunk, 'score' => $score];
            usort($best, fn ($a, $b) => $b['score'] <=> $a['score']);
            $best = array_slice($best, 0, 96);
        }
        $selected = []; $references = []; $counts = [];
        foreach ($best as $candidate) {
            $chunk = $candidate['chunk']; $id = $chunk->source_id;
            if (($counts[$id] ?? 0) >= 4) continue;
            $counts[$id] = ($counts[$id] ?? 0) + 1;
            $source = $sources->get($id);
            if (! isset($references[$id])) {
                $references[$id] = ['id' => $source->id, 'revision' => $source->revision,
                    'reference' => 'S'.($labels[$id] + 1), 'title' => $source->title,
                    'business_status' => $source->business_status];
            }
            $selected[] = ['reference' => $references[$id]['reference'], 'business_status' => $source->business_status, 'text' => $chunk->content];
            if (count($selected) >= (int) config('pbr_knowledge.retrieval_chunks', 12)) break;
        }
        if ($selected === []) throw new RuntimeException('KNOWLEDGE_INDEX_INCOMPLETE');

        return ['sources' => array_values($references), 'excerpts' => $selected];
    }

    private function cosine(array $left, array $right): float
    {
        if (count($left) !== count($right)) throw new RuntimeException('KNOWLEDGE_REINDEX_REQUIRED');
        $dot = 0.0; $a = 0.0; $b = 0.0;
        foreach ($left as $i => $value) { $dot += $value * $right[$i]; $a += $value ** 2; $b += $right[$i] ** 2; }

        return $a > 0 && $b > 0 ? $dot / sqrt($a * $b) : 0.0;
    }
}
