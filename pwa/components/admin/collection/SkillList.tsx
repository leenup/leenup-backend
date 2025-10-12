import { ListGuesser, FieldGuesser } from "@api-platform/admin";
import { TextField } from "react-admin";

export const SkillList = () => (
  <ListGuesser>
    <FieldGuesser source="title" />
    <TextField source="category.title" label="Category" />
    <FieldGuesser source="createdAt" />
  </ListGuesser>
);
