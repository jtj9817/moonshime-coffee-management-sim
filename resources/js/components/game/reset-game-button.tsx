import { useForm } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import React, { useState } from 'react';

import ConfirmDialog from '@/components/ui/confirm-dialog';
import game from '@/routes/game';

export default function ResetGameButton() {
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  const { post, processing } = useForm();

  const handleReset = () => {
    post(game.reset.url(), {
      onSuccess: () => {
        setIsConfirmOpen(false);
      },
    });
  };

  return (
    <>
      <button
        onClick={() => setIsConfirmOpen(true)}
        disabled={processing}
        className="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
        title="Reset Game to Day 1"
      >
        <RotateCcw className={`h-4 w-4 ${processing ? 'animate-spin' : ''}`} />
        <span className="hidden sm:inline">Reset Game</span>
      </button>

      <ConfirmDialog
        isOpen={isConfirmOpen}
        title="Reset Game?"
        message="Are you sure you want to reset your game? This will delete all your current progress, inventory, and orders, and restart you at Day 1 with default funds. This action cannot be undone."
        confirmLabel={processing ? "Resetting..." : "Yes, Reset Game"}
        isDestructive={true}
        onConfirm={handleReset}
        onCancel={() => setIsConfirmOpen(false)}
      />
    </>
  );
}
