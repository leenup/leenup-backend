import { ListGuesser, FieldGuesser } from "@api-platform/admin";
import { ReferenceField, TextField } from "react-admin";

export const SkillList = () => (
  <ListGuesser>
    <FieldGuesser source="title" />
    <ReferenceField source="category" reference="categories" link="show">
      <TextField source="title" />
    </ReferenceField>
    <FieldGuesser source="createdAt" />
  </ListGuesser>
);
