# DU Event Mini

Provides mini calendar functionality for displaying compact event feeds on University of Denver unit sites.

## Features

- **Mini Calendar Paragraph**: Renders a compact event list view with configurable filters
- **Manual Event Items**: Allows manually selected events to be displayed in mini calendar format

## Architecture

### Hooks (du_event_mini.module)

| Hook | Purpose |
|------|---------|
| `hook_preprocess_paragraph__mini_calendar()` | Extracts filter configuration (audiences, types, unit) and embeds the `event_list` view with `mini` display |
| `hook_preprocess_paragraph__mini_calendar_manual_item()` | Provides URL and title variables for manually referenced event nodes |

### View Integration

The module uses the `event_list` view with the `mini` display ID, passing contextual filters:

| Argument | Description |
|----------|-------------|
| Unit | Taxonomy term ID for unit filtering |
| Include Children | Whether to include child unit terms |
| Audiences | Array of audience taxonomy term IDs |
| Types | Array of event type taxonomy term IDs |
| Audience Join | `or` / `and` logic for audience filters |
| Type Join | `or` / `and` logic for type filters |

## Paragraph Types

- **`mini_calendar`**: Configurable mini event list with taxonomy filters
- **`mini_calendar_manual_item`**: Single manually-selected event reference

## Configuration

Filter options are configured per paragraph instance via fields:

- `field_event_list_aud_filters`: Audience taxonomy references
- `field_audience_filter_join_type`: AND/OR join for audiences
- `field_event_list_type_filters`: Event type taxonomy references
- `field_type_filter_join_type`: AND/OR join for types
- `field_unit`: Unit taxonomy reference
- `field_include_child_units`: Boolean for child unit inclusion
