import { exec } from 'child_process';
import { promisify } from 'util';
import fs from 'fs';
import path from 'path';

const execAsync = promisify(exec);

export async function resetDatabase() {
    try {
        const dbPath = path.resolve('database/e2e.sqlite');
        // Delete the file if it exists to ensure no locks/state remain
        if (fs.existsSync(dbPath)) {
            try {
                fs.unlinkSync(dbPath);
            } catch (e) {
                // Ignore if unlink fails (e.g. busy), though on Linux it usually works
                console.warn('Failed to unlink db, trying to overwrite', e);
            }
        }
        // Create empty file
        fs.writeFileSync(dbPath, '');

        // Run migrate (not fresh, since file is new)
        await execAsync('php artisan migrate --seed --env=testing');
    } catch (error) {
        console.error('Error resetting database:', error);
        throw error;
    }
}
